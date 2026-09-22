<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingBatch;
use App\Domain\Funding\FundingCategory;
use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\Enums\CapexOpex;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\WorkPackage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One debit or one credit against one account with its full dimension set -- the
 * only financial fact in the system (Document B §1.1).
 *
 * Amounts are whole dinars and read back as integers.
 *
 * @property int $id
 * @property int $journal_header_id
 * @property int $line_no
 * @property int $account_id
 * @property int $debit
 * @property int $credit
 * @property int $project_id
 * @property int|null $cost_center_id
 * @property int|null $resp_center_id
 * @property int|null $counterparty_id
 * @property int|null $advance_holder_id
 * @property int|null $cash_account_id
 * @property int|null $funding_source_id
 * @property int|null $funding_batch_id
 * @property int|null $funding_category_id
 * @property int|null $contract_id
 * @property int|null $work_package_id
 * @property int|null $chain_step_id
 * @property int|null $advance_id
 * @property CarbonImmutable|null $settlement_deadline
 * @property CapexOpex|null $capex_opex
 * @property string|null $asset_class
 * @property string|null $handover_req
 * @property bool|null $revenue_eligible
 * @property string|null $eligibility_reason
 * @property int|null $source_amount
 * @property int|null $amount_difference
 * @property string|null $vr05_exemption_ref
 */
class JournalLine extends Model
{
    protected $table = 'journal_lines';

    /** Every dimension a line may carry; copied onto mirror and reclassification lines. */
    public const DIMENSIONS = [
        'project_id', 'cost_center_id', 'vr05_exemption_ref', 'resp_center_id', 'rc_derived', 'counterparty_id',
        'advance_holder_id', 'cash_account_id', 'funding_source_id', 'funding_batch_id', 'funding_category_id',
        'contract_id', 'work_package_id', 'chain_step_id', 'capex_opex', 'asset_class', 'handover_req',
        'revenue_eligible', 'eligibility_reason',
    ];

    protected $fillable = [
        'journal_header_id', 'line_no', 'account_id', 'debit', 'credit', ...self::DIMENSIONS,
        'advance_id', 'settlement_deadline', 'recon_status', 'source_amount', 'amount_difference',
        'actual_payment_source', 'actual_receiver', 'duplicate_review', 'date_comparison', 'source_match_ref', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'debit' => 'integer',
            'credit' => 'integer',
            'source_amount' => 'integer',
            'amount_difference' => 'integer',
            'rc_derived' => 'boolean',
            'revenue_eligible' => 'boolean',
            'capex_opex' => CapexOpex::class,
            'settlement_deadline' => 'immutable_date',
        ];
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function amount(): int
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function header(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /** @return BelongsTo<ResponsibilityCenter, $this> */
    public function responsibilityCenter(): BelongsTo
    {
        return $this->belongsTo(ResponsibilityCenter::class, 'resp_center_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function advanceHolder(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'advance_holder_id');
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'cash_account_id');
    }

    /** @return BelongsTo<FundingSource, $this> */
    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id');
    }

    /** @return BelongsTo<FundingBatch, $this> */
    public function fundingBatch(): BelongsTo
    {
        return $this->belongsTo(FundingBatch::class, 'funding_batch_id');
    }

    /** @return BelongsTo<FundingCategory, $this> */
    public function fundingCategory(): BelongsTo
    {
        return $this->belongsTo(FundingCategory::class, 'funding_category_id');
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /** @return BelongsTo<WorkPackage, $this> */
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    /** @return BelongsTo<ChainStep, $this> */
    public function chainStep(): BelongsTo
    {
        return $this->belongsTo(ChainStep::class, 'chain_step_id');
    }
}
