<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Account;
use App\Domain\Reporting\Contracts\Report;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\ReportDefinition;
use Illuminate\Support\Collection;

/**
 * Shared shape of a report: its title from the catalogue, a printable summary of
 * its filters, and drill-down targets. Report classes never carry account codes:
 * which accounts belong where comes from the chart and the mapping (VR-55).
 */
abstract class BaseReport implements Report
{
    public function __construct(protected readonly LedgerQuery $ledger) {}

    public function permission(): string
    {
        return 'reports.view';
    }

    protected function title(): string
    {
        $definition = ReportDefinition::query()->where('code', $this->code())->first();

        return $definition === null ? $this->code() : $this->code().' — '.$definition->displayName();
    }

    /**
     * The filter set as printed at the head of an export: every filter that is set,
     * by its label, plus the range the report actually covered.
     *
     * @return array<string, string>
     */
    protected function describe(FilterSet $filters, bool $asAt = false): array
    {
        $summary = $asAt
            ? [__('reports.as_at') => $filters->asAt()->toDateString()]
            : [__('reports.range') => $filters->from()->toDateString().' — '.$filters->to()->toDateString()];

        foreach ($filters->toArray() as $key => $value) {
            if (in_array($key, ['from', 'to', 'as_at', 'period', 'fiscal_year'], true)) {
                continue;
            }

            $summary[__('reports.filters.'.$key)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function drillToReport(string $code, FilterSet $filters, array $overrides = []): array
    {
        return ['report' => $code, 'filters' => $filters->with($overrides)->toArray()];
    }

    /** @return Collection<int, Account> */
    protected function accounts(): Collection
    {
        return Account::query()->orderBy('code')->get()->keyBy('id');
    }

    protected function name(Account $account): string
    {
        return $account->displayName();
    }
}
