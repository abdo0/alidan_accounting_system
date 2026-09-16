<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Journal;
use App\Domain\Ledger\JournalEntry;
use App\Domain\Organisation\FiscalPeriod;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only writer of journal_lines.
 *
 * LOCK ORDER -- fixed, and the reason this is written down rather than left implicit:
 *
 *   1. fiscal_periods  FOR SHARE   (so the period cannot close under us)
 *   2. sequence_counters FOR UPDATE (gapless number + hash chain grain)
 *   3. gl_balances upserts, key-sorted (BalanceUpdater)
 *
 * PeriodCloseService takes the period FOR UPDATE first and re-runs its gates after
 * acquiring it -- gates evaluated before the lock are stale. Any new code taking more
 * than one of these must follow the same order or it will deadlock.
 */
final class PostingService
{
    public function __construct(
        private readonly PostingValidator $validator,
        private readonly CostCentreResolver $costCentres,
        private readonly Sequencer $sequencer,
        private readonly BalanceUpdater $balances,
        private readonly EntryHasher $hasher,
    ) {}

    public function post(JournalEntryDraft $draft, ?User $actor = null): JournalEntry
    {
        $actor ??= auth()->user();

        if (! $actor instanceof User && ! $draft->isSystemGenerated) {
            throw new RuntimeException('A journal entry needs an author.');
        }

        return DB::transaction(function () use ($draft, $actor): JournalEntry {
            if ($actor instanceof User) {
                DatabaseContext::setLocal('app.user_id', (string) $actor->id);
            }

            // Idempotency first: INSERT ... ON CONFLICT DO NOTHING inside the
            // transaction. A SELECT-then-INSERT would be a TOCTOU race and would
            // defeat the point, including for deadlock retries.
            if ($draft->idempotencyKey !== null) {
                $existing = $this->claimIdempotencyKey($draft);

                if ($existing !== null) {
                    return $existing;
                }
            }

            // 1. Period lock. Without FOR SHARE a concurrent close can commit between
            //    validation and our commit, and the entry lands in a closed period
            //    where nobody will ever see it.
            $period = $this->lockPeriod($draft);

            // Resolve dimensions BEFORE validating, or V-07 rejects lines that would
            // have defaulted correctly.
            $resolved = $this->costCentres->apply($draft, $actor instanceof User ? $actor : null);

            $this->validator->assertValid($resolved, $period, $actor instanceof User ? $actor : null);

            $journal = Journal::query()->where('code', $resolved->journalCode)->firstOrFail();

            $entry = $this->writeHeader($resolved, $period, $journal, $actor);

            // 2. Sequence lock, taken as late as possible: validation is done, so the
            //    per-journal serialisation lock is held only across the writes.
            $entry->entry_no = $this->sequencer->next(
                scope: 'journal_entry',
                scopeKey: sprintf('%d:%d:%d', $resolved->entityId, $journal->id, $period->fiscal_year_id),
                prefix: $journal->sequence_prefix.'-',
            );

            $this->writeLines($entry, $resolved, $period);
            $entry->load('lines');

            // The hash covers the lines, so it can only be computed once they exist,
            // and it has to be written by the SAME statement that marks the entry
            // posted: from that moment the row is immutable and a follow-up update is
            // refused by the database.
            $chain = $this->hasher->chain($entry, $period->fiscal_year_id);

            $entry->forceFill([
                'status' => JournalEntry::POSTED,
                'posted_at' => now(),
                'posted_by' => $actor instanceof User ? $actor->id : null,
                'prev_entry_hash' => $chain['prev'],
                'entry_hash' => $chain['hash'],
            ])->save();

            // 3. Balances, key-sorted inside the updater.
            $this->balances->apply($entry);

            if ($draft->idempotencyKey !== null) {
                $this->recordIdempotentResult($draft, $entry);
            }

            return $entry->fresh(['lines']) ?? $entry;
        });
    }

    private function lockPeriod(JournalEntryDraft $draft): FiscalPeriod
    {
        $period = FiscalPeriod::query()
            ->where('entity_id', $draft->entityId)
            ->whereDate('starts_on', '<=', $draft->entryDate)
            ->whereDate('ends_on', '>=', $draft->entryDate)
            ->where('is_adjustment_period', false)
            ->sharedLock()
            ->first();

        if (! $period instanceof FiscalPeriod) {
            throw new PostingException([[
                'rule' => 'V-03',
                'message' => __('accounting.validation.date_outside_period', [
                    'date' => $draft->entryDate->toDateString(),
                    'period' => '-',
                ]),
            ]]);
        }

        return $period;
    }

    private function writeHeader(
        JournalEntryDraft $draft,
        FiscalPeriod $period,
        Journal $journal,
        ?User $actor,
    ): JournalEntry {
        return JournalEntry::query()->create([
            'entity_id' => $draft->entityId,
            'journal_id' => $journal->id,
            'fiscal_period_id' => $period->id,
            'entry_date' => $draft->entryDate,
            'posting_date' => $draft->effectivePostingDate(),
            'description' => $draft->description,
            'description_ar' => $draft->descriptionAr,
            'currency_code' => $draft->currencyCode,
            'exchange_rate' => 1,
            'source_type' => $draft->sourceType,
            'source_id' => $draft->sourceId,
            'source_document_no' => $draft->sourceDocumentNo,
            'source_document_date' => $draft->sourceDocumentDate,
            'status' => JournalEntry::APPROVED,
            'is_adjusting' => $draft->isAdjusting,
            'is_closing' => $draft->isClosing,
            'is_opening' => $draft->isOpening,
            'is_system_generated' => $draft->isSystemGenerated,
            'reverses_entry_id' => $draft->reversesEntryId,
            'reversal_reason' => $draft->reversalReason,
            'auto_reverse_on' => $draft->autoReverseOn,
            'total_debit' => $draft->totalDebit(),
            'total_credit' => $draft->totalCredit(),
            'created_by' => $actor instanceof User ? $actor->id : $this->systemUserId(),
        ]);
    }

    private function writeLines(JournalEntry $entry, JournalEntryDraft $draft, FiscalPeriod $period): void
    {
        $now = now();
        $rows = [];

        foreach ($draft->lines as $index => $line) {
            $rows[] = [
                'journal_entry_id' => $entry->id,
                'entity_id' => $draft->entityId,
                'fiscal_period_id' => $period->id,
                'entry_date' => $draft->entryDate->toDateString(),
                'line_no' => $index + 1,
                'account_id' => $line->accountId,
                'cost_centre_id' => $line->costCentreId,
                'project_id' => $line->projectId,
                'currency_code' => $draft->currencyCode,
                'exchange_rate' => 1,
                'debit_amount' => $line->debit,
                'credit_amount' => $line->credit,
                // IQD-only, so functional equals transaction. Written rather than
                // left at zero so reports reading the functional columns are correct.
                'functional_debit' => $line->debit,
                'functional_credit' => $line->credit,
                'description' => $line->description,
                'partner_type' => $line->partnerType,
                'partner_id' => $line->partnerId,
                'tax_code_id' => $line->taxCodeId,
                'tax_base_amount' => $line->taxBaseAmount,
                'quantity' => $line->quantity,
                'uom' => $line->uom,
                'posted_at' => $now,
            ];
        }

        DB::table('journal_lines')->insert($rows);
    }

    private function claimIdempotencyKey(JournalEntryDraft $draft): ?JournalEntry
    {
        $hash = hash('sha256', serialize([
            $draft->entityId, $draft->journalCode, $draft->entryDate->toDateString(),
            $draft->totalDebit(), $draft->totalCredit(), count($draft->lines),
        ]));

        $inserted = DB::affectingStatement(
            'INSERT INTO idempotency_keys (scope, key, request_hash, created_at)
             VALUES (?, ?, ?, now())
             ON CONFLICT (scope, key) DO NOTHING',
            ['journal_entry', $draft->idempotencyKey, $hash]
        );

        if ($inserted > 0) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT result_id FROM idempotency_keys WHERE scope = ? AND key = ?',
            ['journal_entry', $draft->idempotencyKey]
        );

        if ($row?->result_id === null) {
            throw new RuntimeException(
                'A posting with this idempotency key is already in flight.'
            );
        }

        return JournalEntry::query()->with('lines')->findOrFail($row->result_id);
    }

    private function recordIdempotentResult(JournalEntryDraft $draft, JournalEntry $entry): void
    {
        DB::update(
            'UPDATE idempotency_keys SET result_type = ?, result_id = ? WHERE scope = ? AND key = ?',
            [JournalEntry::class, $entry->id, 'journal_entry', $draft->idempotencyKey]
        );
    }

    private function systemUserId(): int
    {
        $id = DB::table('users')->where('is_service_account', true)->value('id');

        if ($id === null) {
            throw new RuntimeException('No service account exists to author system entries.');
        }

        return (int) $id;
    }
}
