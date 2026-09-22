<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reporting;

use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingBatch;
use App\Domain\Funding\FundingCategory;
use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\Enums\CapexOpex;
use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\ValueListItem;
use App\Domain\MasterData\WorkPackage;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\FiscalYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;

/**
 * The filter engine's form (Document C tab 17): one field per filter a report
 * accepts, each bound to the same key the FilterSet reads.
 */
final class ReportFilterForm
{
    /**
     * @param  list<string>  $keys
     * @return list<Field>
     */
    public static function fields(array $keys): array
    {
        return array_values(array_filter(array_map(self::field(...), $keys)));
    }

    private static function field(string $key): ?Field
    {
        $label = __('reports.filters.'.$key);

        return match ($key) {
            'from', 'to', 'as_at', 'txn_from', 'txn_to' => DatePicker::make($key)->label($label),
            'fiscal_year' => Select::make($key)->label($label)->options(fn (): array => FiscalYear::query()->orderBy('year_code')->pluck('year_code', 'id')->all()),
            'period' => Select::make($key)->label($label)->searchable()->options(fn (): array => AccountingPeriod::query()->orderBy('starts_on')->pluck('period_code', 'id')->all()),
            'projects' => self::multi($key, $label, fn (): array => Project::query()->orderBy('code')->pluck('code', 'id')->all()),
            'cost_centers' => self::multi($key, $label, fn (): array => CostCenter::query()->orderBy('code')->pluck('code', 'id')->all()),
            'resp_centers' => self::multi($key, $label, fn (): array => ResponsibilityCenter::query()->orderBy('code')->pluck('code', 'id')->all()),
            'accounts' => self::multi($key, $label, fn (): array => Account::query()->where('is_group', false)->orderBy('code')->get()->mapWithKeys(fn (Account $a): array => [$a->id => $a->label()])->all()),
            'account_groups' => self::multi($key, $label, fn (): array => Account::query()->where('is_group', true)->orderBy('code')->get()->mapWithKeys(fn (Account $a): array => [$a->id => $a->label()])->all()),
            'funding_sources' => self::multi($key, $label, fn (): array => FundingSource::query()->orderBy('code')->pluck('code', 'id')->all()),
            'funding_batches' => self::multi($key, $label, fn (): array => FundingBatch::query()->orderBy('batch_ref')->limit(1000)->pluck('batch_ref', 'id')->all()),
            'funding_categories' => self::multi($key, $label, fn (): array => FundingCategory::query()->orderBy('code')->pluck('code', 'id')->all()),
            'chain_steps' => self::multi($key, $label, fn (): array => ChainStep::query()->orderBy('sort_order')->pluck('code', 'id')->all()),
            'shareholders' => self::multi($key, $label, fn (): array => self::parties(fn ($q) => $q->where('is_shareholder', true))),
            'counterparties' => self::multi($key, $label, fn (): array => self::parties(fn ($q) => $q->where(fn ($q) => $q->where('is_contractor', true)->orWhere('is_supplier', true)))),
            'advance_holders' => self::multi($key, $label, fn (): array => self::parties(fn ($q) => $q->where('is_advance_holder', true))),
            'contracts' => self::multi($key, $label, fn (): array => Contract::query()->pluck('contract_no', 'id')->all()),
            'work_packages' => self::multi($key, $label, fn (): array => WorkPackage::query()->pluck('code', 'id')->all()),
            'capex_opex' => Select::make($key)->label($label)->options(CapexOpex::class),
            'asset_classes' => self::multi($key, $label, fn (): array => self::values('asset_class')),
            'handover' => self::multi($key, $label, fn (): array => self::values('handover_requirement')),
            'statuses' => self::multi($key, $label, fn (): array => JournalStatus::options()),
            'doc_statuses' => self::multi($key, $label, fn (): array => DocStatus::options()),
            'date_statuses' => self::multi($key, $label, fn (): array => DateStatus::options()),
            'recon_statuses' => self::multi($key, $label, fn (): array => self::values('reconciliation_status')),
            'source_presences' => self::multi($key, $label, fn (): array => self::values('source_presence')),
            'flag_types' => self::multi($key, $label, fn (): array => DuplicateFlagType::options()),
            'cash_accounts' => self::multi($key, $label, fn (): array => BankAccount::query()->orderBy('code')->pluck('code', 'id')->all()),
            'comparative' => Select::make($key)->label($label)->options([
                'prior_month' => __('reports.comparative.prior_month'),
                'prior_year_ytd' => __('reports.comparative.prior_year_ytd'),
            ]),
            default => null,
        };
    }

    /** @param  callable(): array<int|string, string>  $options */
    private static function multi(string $key, string $label, callable $options): Select
    {
        return Select::make($key)->label($label)->multiple()->searchable()->options($options);
    }

    /** @return array<int, string> */
    private static function parties(callable $scope): array
    {
        return Counterparty::query()->tap($scope)->orderBy('code')->get()->mapWithKeys(fn (Counterparty $c): array => [$c->id => $c->label()])->all();
    }

    /** @return array<string, string> */
    private static function values(string $list): array
    {
        return ValueListItem::query()->where('list_code', $list)->orderBy('sort_order')->pluck('value', 'value')->all();
    }
}
