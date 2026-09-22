<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Controls\Exceptions\ExceptionRaiser;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\Posting\PostingObserver;
use App\Domain\Ledger\Posting\Sequencer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Keeps the advance register in step with the ledger, inside the posting
 * transaction:
 *
 *   - a debit to an advance account on an issuing entry (TT-07 custody advance,
 *     TT-08 sub-advance, TT-14 contractor or supplier advance) opens an advance;
 *   - any other line on an advance account is linked to the advance it moves --
 *     the one named on the line, or else the holder's oldest open advance on that
 *     account -- as a settlement, refund, reclassification, sub-advance or recovery;
 *   - a touched advance that is past its deadline raises an overdue exception
 *     (Document B §2.2 step 8).
 */
final class AdvanceObserver implements PostingObserver
{
    private const ISSUING_TYPES = ['TT-07', 'TT-08', 'TT-14'];

    public function __construct(
        private readonly Sequencer $sequencer,
        private readonly ExceptionRaiser $exceptions,
    ) {}

    public function posted(JournalHeader $header, User $actor): void
    {
        $header->loadMissing(['lines.account', 'transactionType']);
        $touched = collect();

        foreach ($header->lines as $line) {
            if (! $line->account->is_advance_account) {
                continue;
            }

            $holder = $line->advance_holder_id ?? $line->counterparty_id;

            if ($line->debit > 0 && $header->transactionType->isCode(...self::ISSUING_TYPES) && $holder !== null) {
                $touched->push($this->open($header, $line, $holder));

                continue;
            }

            $advance = $this->advanceFor($header, $line, $holder);

            if ($advance !== null) {
                AdvanceSettlement::query()->create([
                    'advance_id' => $advance->id,
                    'journal_line_id' => $line->id,
                    'settlement_type' => $this->typeOf($header, $line),
                ]);
                $touched->push($advance);
            }
        }

        $this->flagOverdue($touched);
    }

    private function open(JournalHeader $header, JournalLine $line, int $holder): Advance
    {
        $year = $header->posting_date->format('Y');

        return Advance::query()->create([
            'company_id' => $header->company_id,
            'advance_ref' => $this->sequencer->next('advance', 'year:'.$year, 'ADV-'.$year.'-', 4),
            'holder_id' => $holder,
            'account_id' => $line->account_id,
            'project_id' => $line->project_id,
            'cost_center_id' => $line->cost_center_id,
            'resp_center_id' => $line->resp_center_id,
            'issue_journal_id' => $header->id,
            'issue_line_id' => $line->id,
            'purpose' => $header->description_ar,
            'issue_date' => $header->txn_date ?? $header->posting_date,
            'settlement_deadline' => $line->settlement_deadline,
        ]);
    }

    private function advanceFor(JournalHeader $header, JournalLine $line, ?int $holder): ?Advance
    {
        if ($line->advance_id !== null) {
            return Advance::query()->find($line->advance_id);
        }

        // A reversal moves the advance its original line moved.
        if ($header->reversal_of_journal_id !== null) {
            $original = JournalLine::query()
                ->where('journal_header_id', $header->reversal_of_journal_id)
                ->where('line_no', $line->line_no)
                ->first();

            if ($original !== null) {
                return Advance::query()->where('issue_line_id', $original->id)->first()
                    ?? AdvanceSettlement::query()->where('journal_line_id', $original->id)->first()?->advance;
            }
        }

        if ($holder === null) {
            return null;
        }

        return Advance::query()
            ->where('holder_id', $holder)
            ->where('account_id', $line->account_id)
            ->orderBy('issue_date')->orderBy('id')
            ->get()
            ->first(fn (Advance $advance): bool => AdvancePosition::of($advance, $header->posting_date)->outstanding > 0);
    }

    private function typeOf(JournalHeader $header, JournalLine $line): SettlementType
    {
        $type = $header->transactionType->code;

        return match (true) {
            $type === 'TT-09' => $this->capexKind($header),
            $type === 'TT-10' => SettlementType::Opex,
            $type === 'TT-11' => SettlementType::FixedAsset,
            $type === 'TT-12' => SettlementType::Reclassification,
            $type === 'TT-13' => SettlementType::Refund,
            $type === 'TT-08' => SettlementType::SubAdvance,
            $type === 'TT-32' => SettlementType::Shortfall,
            in_array($type, ['TT-16', 'TT-18'], true) => SettlementType::Recovery,
            $type === 'TT-20' => SettlementType::Acquisition,
            default => SettlementType::Other,
        };
    }

    /** TT-09 may settle to CIP, to an acquisition or to a retained fixed asset (PR-09). */
    private function capexKind(JournalHeader $header): SettlementType
    {
        $lines = $header->lines->filter(fn (JournalLine $l): bool => $l->debit > 0);

        return match (true) {
            $lines->contains(fn (JournalLine $l): bool => $l->account->fs_line_code === 'SFP-A-080') => SettlementType::FixedAsset,
            $lines->contains(fn (JournalLine $l): bool => $l->account->fs_line_code === 'SFP-A-050') => SettlementType::Acquisition,
            default => SettlementType::Capex,
        };
    }

    /** @param  Collection<int, Advance>  $advances */
    private function flagOverdue(Collection $advances): void
    {
        foreach ($advances->unique('id') as $advance) {
            $position = AdvancePosition::of($advance);

            if ($position->status() === AdvanceStatus::Overdue) {
                OverdueAdvances::raise($this->exceptions, $position);
            }
        }
    }
}
