<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Domain\Access\Enums\PermissionScope;
use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Advances\Enums\EvidenceStatus;
use App\Domain\Advances\Enums\SettlementClassification;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Enums\ReconciliationType;
use App\Domain\Controls\Enums\DuplicateDisposition;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Funding\Enums\FundingBatchStatus;
use App\Domain\Funding\Enums\FundingSourceType;
use App\Domain\Ledger\Enums\ApprovalAction;
use App\Domain\Ledger\Enums\CapexOpex;
use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Enums\AmendmentType;
use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\MasterData\Enums\BankAccountType;
use App\Domain\MasterData\Enums\ChangeRequestAction;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Domain\MasterData\Enums\ContractStatus;
use App\Domain\MasterData\Enums\ContractType;
use App\Domain\MasterData\Enums\CostCenterType;
use App\Domain\MasterData\Enums\CounterpartyType;
use App\Domain\MasterData\Enums\NormalBalance;
use App\Domain\MasterData\Enums\ProjectType;
use App\Domain\Organisation\Enums\FiscalYearStatus;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\Enums\ParameterType;
use App\Domain\Organisation\Enums\PeriodStatus;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The enums and the database must agree about what values exist, and every value
 * must be sayable in both languages.
 *
 * This reads the live CHECK constraints, so the day someone widens a constraint in
 * a migration without adding the case -- or adds a case the database will reject on
 * write -- the build fails here rather than on the first row that uses the value.
 */
class EnumParityTest extends TestCase
{
    /** @return array<string, array{class-string<BackedEnum>, string, string}> */
    public static function constrainedEnums(): array
    {
        return [
            'period status' => [PeriodStatus::class, 'accounting_periods', 'accounting_periods_status_valid'],
            'fiscal year status' => [FiscalYearStatus::class, 'fiscal_years', 'fiscal_years_status_valid'],
            'parameter type' => [ParameterType::class, 'parameters', 'parameters_type_valid'],
            'account type' => [AccountType::class, 'accounts', 'accounts_type_valid'],
            'normal balance' => [NormalBalance::class, 'accounts', 'accounts_normal_balance_valid'],
            'project type' => [ProjectType::class, 'projects', 'projects_type_valid'],
            'cost center type' => [CostCenterType::class, 'cost_centers', 'cost_centers_type_valid'],
            'counterparty type' => [CounterpartyType::class, 'counterparties', 'counterparties_type_valid'],
            'bank account type' => [BankAccountType::class, 'bank_accounts', 'bank_accounts_type_valid'],
            'contract type' => [ContractType::class, 'contracts', 'contracts_type_valid'],
            'contract status' => [ContractStatus::class, 'contracts', 'contracts_status_valid'],
            'amendment type' => [AmendmentType::class, 'contract_amendments', 'contract_amendments_type_valid'],
            'approval status (funding categories)' => [ApprovalStatus::class, 'funding_categories', 'funding_categories_approval_valid'],
            'approval status (value lists)' => [ApprovalStatus::class, 'value_list_items', 'value_list_items_approval_valid'],
            'change request status' => [ChangeRequestStatus::class, 'change_requests', 'change_requests_status_valid'],
            'change request action' => [ChangeRequestAction::class, 'change_requests', 'change_requests_action_valid'],
            'funding source type' => [FundingSourceType::class, 'funding_sources', 'funding_sources_type_valid'],
            'funding batch status' => [FundingBatchStatus::class, 'funding_batches', 'funding_batches_status_valid'],
            'permission scope' => [PermissionScope::class, 'role_permissions', 'role_permissions_scope_valid'],
            'journal status' => [JournalStatus::class, 'journal_headers', 'jh_status_valid'],
            'doc status' => [DocStatus::class, 'journal_headers', 'jh_doc_status_valid'],
            'date status' => [DateStatus::class, 'journal_headers', 'jh_date_status_valid'],
            'capex opex' => [CapexOpex::class, 'journal_lines', 'jl_capex_opex_valid'],
            'approval action' => [ApprovalAction::class, 'approvals', 'approvals_action_valid'],
            'duplicate flag type' => [DuplicateFlagType::class, 'duplicate_flags', 'duplicate_flags_type_valid'],
            'duplicate disposition' => [DuplicateDisposition::class, 'duplicate_flags', 'duplicate_flags_disposition_valid'],
            'exception status' => [ExceptionStatus::class, 'exceptions', 'exceptions_status_valid'],
            'exception category' => [ExceptionCategory::class, 'exceptions', 'exceptions_category_valid'],
            'settlement type' => [SettlementType::class, 'advance_settlements', 'advance_settlements_type_valid'],
            'settlement classification' => [SettlementClassification::class, 'advance_settlements', 'advance_settlements_class_valid'],
            'evidence status' => [EvidenceStatus::class, 'advance_settlements', 'advance_settlements_evidence_valid'],
            'reconciliation type' => [ReconciliationType::class, 'reconciliations', 'reconciliations_type_valid'],
            'reconciliation status' => [ReconciliationStatus::class, 'reconciliations', 'reconciliations_status_valid'],
        ];
    }

    /** @return array<string, array{class-string<BackedEnum>}> */
    public static function labelledEnums(): array
    {
        $enums = [];
        foreach (self::constrainedEnums() as $name => [$enum]) {
            $enums[$enum] = [$enum];
        }

        $enums[ParameterCode::class] = [ParameterCode::class];
        $enums[AdvanceStatus::class] = [AdvanceStatus::class];

        return $enums;
    }

    /** @param  class-string<BackedEnum>  $enum */
    #[Test]
    #[DataProvider('constrainedEnums')]
    public function the_enum_matches_the_database_check_constraint(string $enum, string $table, string $constraint): void
    {
        $definition = DB::scalar(
            'SELECT pg_get_constraintdef(oid) FROM pg_constraint
             WHERE conrelid = ?::regclass AND conname = ?',
            [$table, $constraint],
        );

        $this->assertNotNull($definition, "Constraint {$constraint} is missing from {$table}; the enum has nothing enforcing it.");

        preg_match_all("/'([^']+)'::(?:character varying|bpchar|text)/", (string) $definition, $matches);

        $allowed = array_values(array_unique($matches[1]));
        sort($allowed);

        $cases = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());
        sort($cases);

        $this->assertSame($allowed, $cases, "{$enum} and {$table}.{$constraint} disagree about the allowed values.");
    }

    /** @param  class-string<BackedEnum>  $enum */
    #[Test]
    #[DataProvider('labelledEnums')]
    public function every_case_has_a_label_in_both_languages(string $enum): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->app->setLocale($locale);

            foreach ($enum::cases() as $case) {
                $this->assertInstanceOf(HasLabel::class, $case);

                $label = (string) $case->getLabel();

                $this->assertNotSame('', trim($label), "{$enum}::{$case->name} has an empty {$locale} label.");
                $this->assertStringNotContainsString('enums.', $label, "{$enum}::{$case->name} has no {$locale} translation.");
            }
        }
    }

    #[Test]
    public function every_parameter_of_the_specification_has_a_case(): void
    {
        $refs = array_filter(array_map(fn (ParameterCode $code): ?string => $code->specRef(), ParameterCode::cases()));

        $this->assertCount(15, $refs, 'PARAM-001 ... PARAM-015 each need a case.');
    }
}
