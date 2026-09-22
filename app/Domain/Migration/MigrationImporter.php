<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvanceSettlement;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Exceptions\ExceptionRaiser;
use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingBatch;
use App\Domain\Funding\FundingCategory;
use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\Posting\EntryHasher;
use App\Domain\Ledger\Rules\SelectorParser;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterResolver;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use App\Support\Spec\SpecCsv;
use Carbon\CarbonImmutable;

/**
 * Loads the authoritative journal (M18, Document B §9).
 *
 * Exactly three transformations are applied; everything else is copied:
 *   1  normalisation: one ledger row -> one header and two lines (Dr, Cr), both
 *      inheriting the row's dimensions;
 *   2  responsibility-centre derivation from the advance account, flagged as
 *      system-derived;
 *   3  posting date = transaction date, or the migration cut-off where the source
 *      has none -- the date status keeps the fact, and no date is ever invented.
 *
 * Nothing is rounded, re-dated, re-classified or forced into an account. Where the
 * source is unclear the row migrates with a "Source Exception / Accounting Decision
 * Required" exception carrying its original text. A dry run checks everything and
 * writes nothing but the run record.
 */
final class MigrationImporter
{
    private const SOURCE_DUPLICATE_REVIEWS = ['POSSIBLE AMER DUPLICATE', 'REQUIRES MANUAL REVIEW'];

    /** @var array<string, int|null> */
    private array $lookups = [];

    public function __construct(
        private readonly File1Reader $reader,
        private readonly CounterpartyMatcher $counterparties,
        private readonly ParameterResolver $parameters,
        private readonly ExceptionRaiser $exceptions,
        private readonly EntryHasher $hasher,
        private readonly SelectorParser $selectors,
    ) {}

    /** @return array{run: MigrationRun, errors: list<string>, unresolved: list<string>, rows: int} */
    public function import(User $actor, string $path, bool $commit, ?string $signoff = null, ?string $mappingPath = null, ?string $sheet = null): array
    {
        $this->assertBeforeGoLive();

        if ($commit && ! $actor->hasPermission('migration.run')) {
            throw RuleViolation::because('M18', 'migration.not_authorised');
        }

        $this->counterparties->loadMapping($mappingPath);
        $rows = $this->reader->rows($path, $sheet);
        [$errors, $unresolved] = $this->check($rows);

        $run = MigrationRun::query()->create([
            'run_ref' => 'MIG-'.now()->format('YmdHis'),
            'source_file' => basename($path),
            'source_sha256' => hash_file('sha256', $path),
            'mode' => $commit ? 'commit' : 'dry_run',
            'status' => 'running',
            'signoff_ref' => $signoff,
            'run_by' => $actor->id,
            'started_at' => now(),
        ]);

        if ($commit && ($errors !== [] || $unresolved !== [] || $signoff === null)) {
            $errors = $signoff === null ? [...$errors, __('migration.signoff_required')] : $errors;
            $run->forceFill(['status' => 'failed', 'finished_at' => now(), 'summary' => ['rows' => count($rows), 'errors' => $errors, 'unresolved' => $unresolved]])->save();

            return ['run' => $run, 'errors' => $errors, 'unresolved' => $unresolved, 'rows' => count($rows)];
        }

        if ($commit) {
            DatabaseContext::withAudit('migration_load', 'Migration run '.$run->run_ref, function () use ($actor, $rows, $run, $path): void {
                foreach ($rows as $row) {
                    $this->load($actor, $row, $run, basename($path));
                }

                $this->openHistoricAdvances($run);
                $this->carryRegister();
            });
        }

        $run->forceFill([
            'status' => 'completed',
            'finished_at' => now(),
            'summary' => ['rows' => count($rows), 'errors' => $errors, 'unresolved' => $unresolved],
        ])->save();

        return ['run' => $run, 'errors' => $errors, 'unresolved' => $unresolved, 'rows' => count($rows)];
    }

    /** VR-54: migration entries may not be created after go-live. */
    private function assertBeforeGoLive(): void
    {
        $goLive = $this->parameters->date(ParameterCode::GoLiveDate);

        if ($goLive !== null && ! CarbonImmutable::today()->lessThan($goLive)) {
            throw RuleViolation::because('VR-54', 'validation_rules.VR-54');
        }
    }

    /**
     * @param  list<SourceRow>  $rows
     * @return array{list<string>, list<string>}
     */
    private function check(array $rows): array
    {
        $errors = [];
        $needsCutoff = false;

        foreach ($rows as $row) {
            $where = 'row '.$row->rowNumber.' ('.$row->get('jv_no').')';

            foreach (['debit_account', 'credit_account'] as $field) {
                if ($this->accountId($row->get($field)) === null) {
                    $errors[] = __('migration.unknown_account', ['where' => $where, 'code' => $row->get($field)]);
                }
            }

            if ($row->isFractional('debit') || $row->isFractional('credit')) {
                $errors[] = __('migration.fractional', ['where' => $where]);
            }

            if ($row->amount('debit') === null || $row->amount('debit') !== $row->amount('credit') || $row->amount('debit') <= 0) {
                $errors[] = __('migration.unbalanced', ['where' => $where]);
            }

            if ($this->projectId($row->get('project')) === null) {
                $errors[] = __('migration.unknown_project', ['where' => $where, 'project' => $row->get('project')]);
            }

            if ($row->get('txn_date') === '') {
                $needsCutoff = true;
            }

            $this->counterparties->match($row->get('counterparty'));
        }

        if ($needsCutoff && $this->parameters->date(ParameterCode::MigrationCutoffDate) === null) {
            $errors[] = __('migration.cutoff_required');
        }

        return [$errors, $this->counterparties->unresolved()];
    }

    private function load(User $actor, SourceRow $row, MigrationRun $run, string $file): void
    {
        $txnDate = $row->get('txn_date') === '' ? null : CarbonImmutable::parse($row->get('txn_date'));
        $postingDate = $txnDate ?? $this->parameters->date(ParameterCode::MigrationCutoffDate);
        $period = AccountingPeriod::containing(Company::current()->id, $postingDate)
            ?? throw RuleViolation::because('VR-12', 'migration.no_period', ['date' => $postingDate?->toDateString()]);

        $debitAccount = Account::query()->findOrFail($this->accountId($row->get('debit_account')));
        $creditAccount = Account::query()->findOrFail($this->accountId($row->get('credit_account')));
        $amount = (int) $row->amount('debit');

        $header = JournalHeader::query()->create([
            'company_id' => Company::current()->id,
            'period_id' => $period->id,
            'jv_no' => $row->get('jv_no'),
            'transaction_type_id' => $this->lookup('tt', TransactionType::MIGRATION, fn () => TransactionType::query()->where('code', TransactionType::MIGRATION)->value('id')),
            'txn_date' => $txnDate,
            'posting_date' => $postingDate,
            'description_ar' => $row->get('description_ar') !== '' ? $row->get('description_ar') : '—',
            'date_status' => $row->get('date_status') === '' ? ($txnDate === null ? DateStatus::NoDateInSource : DateStatus::Ok) : DateStatus::fromSource($row->get('date_status')),
            'doc_status' => 'missing',
            'recon_status' => $row->get('recon_status') ?: null,
            'source_presence' => $row->get('source_presence') ?: null,
            'source_reference' => $row->get('source_reference') ?: null,
            'source_file' => $file,
            'source_row' => (string) $row->rowNumber,
            'status' => JournalStatus::Draft,
            'is_migration' => true,
            'migration_run_id' => $run->id,
            'created_by' => $actor->id,
        ]);

        $dimensions = $this->dimensions($row, $debitAccount, $creditAccount);

        foreach ([[1, $debitAccount, $amount, 0], [2, $creditAccount, 0, $amount]] as [$lineNo, $account, $debit, $credit]) {
            $header->lines()->create($dimensions + [
                'line_no' => $lineNo,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'advance_holder_id' => $account->is_advance_account ? ($account->holder_counterparty_id ?? $dimensions['counterparty_id']) : null,
            ]);
        }

        $header->load('lines', 'period');
        $chain = $this->hasher->chain($header, (string) $header->jv_no);
        $header->forceFill([
            'status' => JournalStatus::Posted,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'entry_hash' => $chain['hash'],
            'prev_entry_hash' => $chain['prev'],
        ])->save();

        $this->carryFindings($row, $header, $debitAccount, $creditAccount, $amount);
    }

    /** @return array<string, mixed> */
    private function dimensions(SourceRow $row, Account $debit, Account $credit): array
    {
        $costCenter = $this->costCenterId($row->get('cost_center'));
        $rc = $debit->responsibility_center_id ?? $credit->responsibility_center_id
            ?? $this->lookup('rc', 'RC-CORP', fn () => ResponsibilityCenter::query()->where('code', 'RC-CORP')->value('id'));

        return [
            'project_id' => $this->projectId($row->get('project')),
            'cost_center_id' => $costCenter,
            'vr05_exemption_ref' => $costCenter === null ? 'EXC-SYS-03' : null,
            'resp_center_id' => $rc,
            'rc_derived' => true,
            'counterparty_id' => ($id = $this->counterparties->match($row->get('counterparty'), create: true)) > 0 ? $id : null,
            'funding_batch_id' => $this->batchId($row->get('source_reference')),
            'funding_category_id' => $this->categoryId($row->get('funding_category')),
            'chain_step_id' => $this->chainStepId($row->get('chain_step')),
            'source_amount' => $row->amount('source_amount'),
            'amount_difference' => $row->amount('amount_difference'),
            'actual_receiver' => $row->get('actual_receiver') ?: null,
            'recon_status' => $row->get('recon_status') ?: null,
            'duplicate_review' => $row->get('duplicate_review') ?: null,
            'date_comparison' => $row->get('date_comparison') ?: null,
            'source_match_ref' => trim(implode(' · ', array_filter([$row->get('amer_match_ref_1'), $row->get('amer_match_ref_2'), $row->get('amer_match_ref_3')]))) ?: null,
            'notes' => $row->get('notes') ?: null,
        ];
    }

    /**
     * What the source already knows goes in as it is: its duplicate flags, its
     * amount differences, the missing cost centre, a chain step whose accounts do
     * not match. Each becomes an open flag or exception, resolved by nobody here.
     */
    private function carryFindings(SourceRow $row, JournalHeader $header, Account $debit, Account $credit, int $amount): void
    {
        $review = strtoupper($row->get('duplicate_review'));

        foreach (self::SOURCE_DUPLICATE_REVIEWS as $flagged) {
            if (str_contains($review, $flagged)) {
                DuplicateFlag::query()->create([
                    'journal_header_id' => $header->id,
                    'flag_type' => DuplicateFlagType::SourceCarried,
                    'match_reason' => $row->get('duplicate_review').($row->get('amer_match_ref_1') !== '' ? ' — '.$row->get('amer_match_ref_1') : ''),
                    'score' => str_contains($flagged, 'MANUAL') ? 50 : 70,
                    'value_at_risk' => $amount,
                    'source_review' => $flagged,
                    'origin' => 'migration',
                ]);
            }
        }

        if (($row->amount('amount_difference') ?? 0) !== 0) {
            $this->exceptions->raise(ExceptionCategory::AmountDifference, __('controls.exception.amount_difference', ['jv' => $header->jv_no, 'line' => 1]), [
                'journal_header_id' => $header->id, 'amount' => $row->amount('amount_difference'),
            ], 'difference:'.$header->id);
        }

        if ($this->costCenterId($row->get('cost_center')) === null) {
            $this->exceptions->raise(ExceptionCategory::MissingDimension, __('migration.missing_cost_center', ['jv' => $header->jv_no]), [
                'journal_header_id' => $header->id, 'amount' => $amount,
            ], 'cost_center:'.$header->id);
        }

        $step = $this->chainStepId($row->get('chain_step'));
        if ($step !== null) {
            $chain = ChainStep::query()->find($step);
            $fits = $chain !== null
                && $this->selectors->parse($chain->debit_selector)->contains($debit->code)
                && $this->selectors->parse($chain->credit_selector)->contains($credit->code);

            if (! $fits) {
                $this->exceptions->raise(ExceptionCategory::SourceException, __('migration.chain_mismatch', ['jv' => $header->jv_no, 'step' => $row->get('chain_step')]), [
                    'journal_header_id' => $header->id, 'amount' => $amount, 'description' => $row->get('description_ar'),
                ], 'chain:'.$header->id);
            }
        } elseif ($row->get('chain_step') !== '') {
            $this->exceptions->raise(ExceptionCategory::SourceException, __('migration.unknown_value', ['jv' => $header->jv_no, 'value' => $row->get('chain_step')]), [
                'journal_header_id' => $header->id, 'amount' => $amount,
            ], 'chain-value:'.$header->id);
        }
    }

    /**
     * MIG-17: the source records advances as flows on each custodian's account, not
     * as individual advances. One historic advance per holder and account carries
     * them all, so its outstanding amount is exactly the account's net position --
     * including a net credit, which is shown as it is (MC-14).
     */
    private function openHistoricAdvances(MigrationRun $run): void
    {
        $lines = JournalLine::query()
            ->whereHas('header', fn ($q) => $q->where('migration_run_id', $run->id))
            ->whereHas('account', fn ($q) => $q->where('is_advance_account', true))
            ->with(['account', 'chainStep', 'header'])
            ->get();

        foreach ($lines->groupBy(fn (JournalLine $l): string => $l->account_id.':'.$l->advance_holder_id) as $group) {
            $first = $group->first();

            if ($first->advance_holder_id === null) {
                continue;
            }

            $advance = Advance::query()->firstOrCreate(
                ['holder_id' => $first->advance_holder_id, 'account_id' => $first->account_id, 'is_historic' => true],
                [
                    'company_id' => Company::current()->id,
                    'advance_ref' => 'ADV-HIST-'.$first->account->code.'-'.$first->advance_holder_id,
                    'resp_center_id' => $first->resp_center_id,
                    'issue_date' => $group->min(fn (JournalLine $l) => $l->header->posting_date),
                    'purpose' => __('migration.historic_advance'),
                ],
            );

            foreach ($group as $line) {
                AdvanceSettlement::query()->firstOrCreate(['journal_line_id' => $line->id], [
                    'advance_id' => $advance->id,
                    'settlement_type' => $this->settlementType($line),
                ]);
            }
        }
    }

    /**
     * MIG-15: the exceptions of the register that concern ledger rows (EXC-OPEN-01
     * ... 17, EXC-SYS-03) migrate open, at their true value, with the ledger.
     */
    private function carryRegister(): void
    {
        foreach (SpecCsv::rows('24_Exceptions', ['Exception ID', 'Category', 'Subject', 'Amount / Volume', 'Description', 'Required action']) as $row) {
            if (ControlException::query()->where('source_code', $row['Exception ID'])->exists()) {
                continue;
            }

            $amount = preg_match('/IQD\s+([\d,]+)/', $row['Amount / Volume'], $m) === 1 ? (int) str_replace(',', '', $m[1]) : null;

            ControlException::query()->create([
                'company_id' => Company::current()->id,
                'exception_no' => $row['Exception ID'],
                'source_code' => $row['Exception ID'],
                'category' => self::registerCategory($row['Category']),
                'raised_date' => Company::current()->accounting_start,
                'subject' => $row['Subject'],
                'description' => $row['Description'],
                'amount' => $amount,
                'volume' => $row['Amount / Volume'],
                'required_action' => $row['Required action'],
                'status' => 'open',
            ]);
        }
    }

    private static function registerCategory(string $category): ExceptionCategory
    {
        return match (strtolower($category)) {
            'open control difference' => ExceptionCategory::OpenControlDifference,
            'stage difference' => ExceptionCategory::StageDifference,
            'unidentified source receipt' => ExceptionCategory::UnidentifiedReceipt,
            'probable duplicate held unposted' => ExceptionCategory::ProbableDuplicate,
            'potential duplicates carried in the ledger' => ExceptionCategory::PotentialDuplicates,
            'suspense item' => ExceptionCategory::SuspenseItem,
            'review required — possible duplicate' => ExceptionCategory::ReviewRequired,
            'classification review required' => ExceptionCategory::ClassificationReview,
            'unpriced source line' => ExceptionCategory::UnpricedSourceLine,
            'undetermined reclassifications' => ExceptionCategory::UndeterminedReclassification,
            'amount under review' => ExceptionCategory::AmountUnderReview,
            'contractor account not closed' => ExceptionCategory::ContractorAccountOpen,
            'undated historical entries' => ExceptionCategory::UndatedEntries,
            'entries with inconsistent dates' => ExceptionCategory::InconsistentDates,
            'anonymised payees' => ExceptionCategory::AnonymisedPayees,
            'pending-evidence settlements' => ExceptionCategory::PendingEvidence,
            'missing dimension' => ExceptionCategory::MissingDimension,
            default => ExceptionCategory::SourceException,
        };
    }

    private function settlementType(JournalLine $line): SettlementType
    {
        return match ($line->chainStep?->code) {
            'CS-3S', 'CS-3SA' => SettlementType::Capex,
            'CS-XRC' => SettlementType::Reclassification,
            'CS-XSS' => SettlementType::Shortfall,
            'CS-2SA' => SettlementType::SubAdvance,
            'CS-R' => SettlementType::Recovery,
            default => SettlementType::Other,
        };
    }

    private function accountId(string $code): ?int
    {
        return $this->lookup('account', $code, fn () => Account::query()->where('code', $code)->where('is_group', false)->value('id'));
    }

    private function projectId(string $code): ?int
    {
        return $this->lookup('project', $code, fn () => Project::query()->where('code', $code)->value('id'));
    }

    private function costCenterId(string $value): ?int
    {
        return $value === '' ? null : $this->lookup('cc', $value, fn () => CostCenter::query()->where('code', $value)->orWhere('name', $value)->orWhere('name_ar', $value)->value('id'));
    }

    private function categoryId(string $value): ?int
    {
        return $value === '' ? null : $this->lookup('fc', $value, fn () => FundingCategory::query()->where('code', $value)->orWhere('name', $value)->orWhere('name_ar', $value)->value('id'));
    }

    private function chainStepId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        return $this->lookup('cs', $value, function () use ($value): ?int {
            $english = trim(explode(' / ', $value)[0]);

            return ChainStep::query()->where('code', $value)->orWhere('label', $english)->value('id');
        });
    }

    /** One batch per source reference (RE-04, MIG-10), series taken from the prefix. */
    private function batchId(string $reference): ?int
    {
        if ($reference === '') {
            return null;
        }

        return $this->lookup('batch', $reference, fn () => FundingBatch::query()->firstOrCreate(
            ['company_id' => Company::current()->id, 'batch_ref' => $reference],
            ['series' => preg_match('/^([A-Z]+(?:-[A-Z]+)*)/', $reference, $m) === 1 ? $m[1] : null, 'status' => 'posted'],
        )->id);
    }

    private function lookup(string $kind, string $key, callable $resolve): ?int
    {
        $cacheKey = $kind.':'.$key;

        if (! array_key_exists($cacheKey, $this->lookups)) {
            $value = $resolve();
            $this->lookups[$cacheKey] = $value === null ? null : (int) $value;
        }

        return $this->lookups[$cacheKey];
    }
}
