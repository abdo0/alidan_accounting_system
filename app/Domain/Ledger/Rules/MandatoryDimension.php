<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Rules;

use InvalidArgumentException;

/**
 * The "Mandatory dimensions" vocabulary of Document C tab 13, and where on an entry
 * each one must be present.
 *
 * Some tokens restate a rule the validation chain already enforces for every entry
 * (the cash account on 111xxx lines is VR-06; the eligibility flag on revenue lines
 * is VR-42). They are accepted here and satisfied by that rule, so the vocabulary
 * stays complete without checking the same thing twice.
 */
enum MandatoryDimension: string
{
    case Project = 'project';
    case CostCenter = 'cost_center';
    case RespCenter = 'resp_center';
    case AdvanceHolder = 'advance_holder_id';
    case Counterparty = 'counterparty_id';
    case Shareholder = 'shareholder_id';
    case CashAccount = 'cash_account_id';
    case FundingSource = 'funding_source';
    case FundingBatch = 'funding_batch_id';
    case Contract = 'contract_id';
    case WorkPackage = 'work_package_id';
    case CapexOpex = 'capex_opex';
    case CapexOpexCapex = 'capex_opex=CAPEX';
    case CapexOpexOpex = 'capex_opex=OPEX';
    case AssetClass = 'asset_class';
    case HandoverReq = 'handover_req';
    case ProjectPrj02 = 'project=PRJ-02';
    case ApprovalRef = 'approval_ref';
    case DocumentRef = 'document_ref';
    case ResolutionRef = 'resolution_ref';
    case LinkedJournal = 'linked_jv_id';
    case SettlementDeadline = 'settlement_deadline';
    case RevenueEligibility = 'revenue_eligibility_flag';
    // Satisfied by other rules or by the engine itself.
    case BothCashAccounts = 'both cash_account_id';
    case AllDimensionsOfFinalAccount = 'all dimensions of the final account';
    case OriginalDimensions = 'original dimensions';
    case Period = 'period';
    case FiscalYear = 'fiscal_year';
    case GovShareRateParam = 'gov_share_rate_param';

    public static function fromToken(string $token): self
    {
        return self::tryFrom(trim($token))
            ?? throw new InvalidArgumentException("Unknown mandatory dimension \"{$token}\".");
    }

    /** Whether another rule or the engine already guarantees it. */
    public function isCoveredElsewhere(): bool
    {
        return in_array($this, [
            self::CashAccount, self::BothCashAccounts, self::RevenueEligibility,
            self::AllDimensionsOfFinalAccount, self::OriginalDimensions, self::Period,
            self::FiscalYear, self::GovShareRateParam,
        ], true);
    }
}
