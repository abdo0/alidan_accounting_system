<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Controls\Duplicates\DuplicateDetector;
use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\ValidationChain;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and edits Draft entries. Draft is the only editable state, and only by
 * its creator or a holder of the all-scope edit permission (Document B §4.2).
 *
 * The header's period is derived from the posting date. Amounts arrive as entered
 * and are refused unless they are whole dinars (VR-19) -- never rounded.
 */
final class JournalService
{
    /** Header attributes a draft may set. */
    private const HEADER_FIELDS = [
        'transaction_type_id', 'posting_rule_id', 'txn_date', 'posting_date', 'description_ar', 'description_en',
        'date_status', 'doc_status', 'doc_ref', 'pv_no', 'rv_no', 'source_reference', 'source_presence',
        'approval_ref', 'resolution_ref', 'linked_journal_id', 'soft_close_reason', 'recon_status',
    ];

    /** Line attributes a draft may set. */
    private const LINE_FIELDS = [
        'account_id', 'debit', 'credit', 'project_id', 'cost_center_id', 'resp_center_id', 'counterparty_id',
        'advance_holder_id', 'cash_account_id', 'funding_source_id', 'funding_batch_id', 'funding_category_id',
        'contract_id', 'work_package_id', 'chain_step_id', 'advance_id', 'settlement_deadline', 'capex_opex',
        'asset_class', 'handover_req', 'revenue_eligible', 'eligibility_reason', 'actual_payment_source',
        'actual_receiver', 'recon_status', 'notes',
    ];

    /**
     * Database constraints that enforce a specification rule. When the storage
     * layer refuses a draft, the user is told which rule, not which constraint.
     */
    private const CONSTRAINT_RULES = [
        'ck_one_side' => ['VR-03', []],
        'ck_cc_or_exemption' => ['VR-05', []],
        'ck_dates' => ['VR-12', ['txn' => '—', 'posting' => '—']],
        'ck_doc_ref_when_complete' => ['VR-14', []],
        'ck_undated' => ['VR-15', []],
        'iqd_amount_check' => ['VR-19', ['amount' => '—']],
    ];

    public function __construct(
        private readonly ValidationChain $chain,
        private readonly DuplicateDetector $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveDraft(User $actor, array $header, array $lines, ?JournalHeader $existing = null): JournalHeader
    {
        $this->authorise($actor, $existing);
        $this->assertWholeDinars($lines);

        try {
            return $this->persist($actor, $header, $lines, $existing);
        } catch (QueryException $e) {
            throw $this->translate($e);
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    private function persist(User $actor, array $header, array $lines, ?JournalHeader $existing): JournalHeader
    {
        return DB::transaction(function () use ($actor, $header, $lines, $existing): JournalHeader {
            $attributes = array_intersect_key($header, array_flip(self::HEADER_FIELDS));
            $postingDate = CarbonImmutable::parse((string) ($attributes['posting_date'] ?? now()->toDateString()));
            $period = AccountingPeriod::containing(Company::current()->id, $postingDate)
                ?? throw new JournalRejected([new Violation('VR-12', Severity::Blocking, __('validation_rules.VR-12_period', ['posting' => $postingDate->toDateString(), 'period' => '—']))]);

            $attributes += [
                'date_status' => ($attributes['txn_date'] ?? null) === null ? DateStatus::NoDateInSource->value : DateStatus::Ok->value,
                'doc_status' => DocStatus::Missing->value,
            ];

            $journal = $existing ?? new JournalHeader([
                'status' => JournalStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $journal->fill($attributes + ['period_id' => $period->id]);
            $journal->save();

            $journal->lines()->delete();
            foreach ($lines as $index => $line) {
                $journal->lines()->create(array_intersect_key($line, array_flip(self::LINE_FIELDS)) + [
                    'line_no' => $index + 1,
                    'debit' => 0,
                    'credit' => 0,
                ]);
            }

            $journal->unsetRelation('lines');
            $result = $this->chain->run($journal, $actor, Checkpoint::Save);

            if ($result->hasBlocking()) {
                throw new JournalRejected($result->blocking());
            }

            // VR-30 runs on save: a match is flagged now, and posting waits for its
            // disposition.
            $journal->load('transactionType');
            $this->duplicates->scan($journal);

            return $journal->fresh(['lines']) ?? $journal;
        });
    }

    private function translate(QueryException $e): \Throwable
    {
        foreach (self::CONSTRAINT_RULES as $constraint => [$rule, $params]) {
            if (str_contains($e->getMessage(), $constraint)) {
                return new JournalRejected([new Violation($rule, Severity::Blocking, __('validation_rules.'.$rule, $params))]);
            }
        }

        return $e;
    }

    public function deleteDraft(User $actor, JournalHeader $journal): void
    {
        $this->authorise($actor, $journal);
        $journal->delete();
    }

    private function authorise(User $actor, ?JournalHeader $existing): void
    {
        if ($existing === null) {
            if (! $actor->hasPermission('journal.create')) {
                throw new AuthorizationException(__('rules.journal.not_authorised'));
            }

            return;
        }

        if ($existing->status !== JournalStatus::Draft) {
            throw new JournalRejected([new Violation('VR-18', Severity::Blocking, __('validation_rules.VR-18'))]);
        }

        if (! $actor->hasPermissionOver('journal.edit_draft', $existing->created_by)) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function assertWholeDinars(array $lines): void
    {
        $violations = [];

        foreach ($lines as $index => $line) {
            foreach (['debit', 'credit'] as $side) {
                $value = trim((string) ($line[$side] ?? '0'));

                if ($value !== '' && preg_match('/^\d+(\.0+)?$/', $value) !== 1) {
                    $violations[] = new Violation('VR-19', Severity::Blocking, __('validation_rules.VR-19', ['amount' => $value]), $index + 1);
                }
            }
        }

        if ($violations !== []) {
            throw new JournalRejected($violations);
        }
    }
}
