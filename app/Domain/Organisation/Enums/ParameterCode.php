<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The system parameters (Document C tab 27, PARAM-001 ... PARAM-015) and the
 * settings the specification refers to without giving a value. Those last load as
 * NULL: the system reads them, it never supplies them.
 */
enum ParameterCode: string implements HasLabel
{
    use TranslatesEnum;

    case LegalEntity = 'legal_entity';
    case EntityCode = 'entity_code';
    case OperatingModel = 'operating_model';
    case ReportingCurrency = 'reporting_currency';
    case PresentationUnits = 'presentation_units';
    case FxRateIqdPerUsd = 'fx_rate_iqd_per_usd';
    case GovShareRate = 'gov_share_rate';
    case ContractInitialTermYears = 'contract_initial_term_years';
    case CommercialOperationDate = 'commercial_operation_date';
    case AccountingStart = 'accounting_start';
    case GovernmentCounterparty = 'government_counterparty';
    case AmortizationStartRule = 'amortization_start_rule';
    case SuspenseAccount = 'suspense_account';
    case RetentionPctDefault = 'retention_pct_default';
    case BaseCashAccountPrj01 = 'base_cash_account_prj01';
    case ContractThresholdVr29 = 'contract_threshold_vr29';
    case MigrationCutoffDate = 'migration_cutoff_date';
    case GoLiveDate = 'go_live_date';
    case DuplicateNearDateDays = 'duplicate_near_date_days';

    public static function translationKey(): string
    {
        return 'parameter_code';
    }

    /** The PARAM-nnn of Document C tab 27, where the spec numbers it. */
    public function specRef(): ?string
    {
        return match ($this) {
            self::LegalEntity => 'PARAM-001',
            self::EntityCode => 'PARAM-002',
            self::OperatingModel => 'PARAM-003',
            self::ReportingCurrency => 'PARAM-004',
            self::PresentationUnits => 'PARAM-005',
            self::FxRateIqdPerUsd => 'PARAM-006',
            self::GovShareRate => 'PARAM-007',
            self::ContractInitialTermYears => 'PARAM-008',
            self::CommercialOperationDate => 'PARAM-009',
            self::AccountingStart => 'PARAM-010',
            self::GovernmentCounterparty => 'PARAM-011',
            self::AmortizationStartRule => 'PARAM-012',
            self::SuspenseAccount => 'PARAM-013',
            self::RetentionPctDefault => 'PARAM-014',
            self::BaseCashAccountPrj01 => 'PARAM-015',
            default => null,
        };
    }

    public static function fromSpecRef(string $ref): self
    {
        foreach (self::cases() as $case) {
            if ($case->specRef() === $ref) {
                return $case;
            }
        }

        throw new \InvalidArgumentException("Unknown parameter reference {$ref}.");
    }

    public function dataType(): ParameterType
    {
        return match ($this) {
            self::FxRateIqdPerUsd, self::GovShareRate, self::RetentionPctDefault => ParameterType::Decimal,
            self::ContractInitialTermYears, self::ContractThresholdVr29, self::DuplicateNearDateDays => ParameterType::Integer,
            self::CommercialOperationDate, self::AccountingStart, self::MigrationCutoffDate, self::GoLiveDate => ParameterType::Date,
            default => ParameterType::String,
        };
    }

    /**
     * Changes to these are logged as high-risk actions (Document C tab 19, ROLE-05)
     * and need a board reference.
     */
    public function isHighRisk(): bool
    {
        return in_array($this, [self::CommercialOperationDate, self::GovShareRate], true);
    }
}
