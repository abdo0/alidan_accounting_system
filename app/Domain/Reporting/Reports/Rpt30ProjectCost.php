<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Project;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * RPT-30 Project cost to date: per project and cost centre -- CIP additions,
 * operating costs, administrative costs, fixed assets and the total. The columns
 * are statement lines, so the grouping follows the mapping, not a code list.
 */
final class Rpt30ProjectCost extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-30';
    }

    public function filters(): array
    {
        return ['from', 'to', 'projects', 'cost_centers', 'capex_opex'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $columns = [
            'cip' => $this->sets->onLine('SFP-A-040'),
            'operating' => $this->sets->onLine('PL-020', 'PL-040'),
            'administrative' => $this->sets->onLine('PL-060', 'PL-070'),
            'fixed_assets' => $this->sets->onLine('SFP-A-080'),
        ];

        $all = array_merge(...array_values($columns));
        $data = $this->ledger->lines($filters)
            ->whereIn('jl.account_id', $all)
            ->groupBy('jl.project_id', 'jl.cost_center_id', 'jl.account_id')
            ->selectRaw('jl.project_id, jl.cost_center_id, jl.account_id, sum(jl.debit) - sum(jl.credit) AS net')
            ->get();

        $projects = Project::query()->pluck('code', 'id');
        $centres = CostCenter::query()->pluck('code', 'id');
        $rows = [];
        $totals = array_fill_keys([...array_keys($columns), 'total'], 0);

        foreach ($data->groupBy('project_id') as $projectId => $byProject) {
            $rows[] = new Row(['project' => $projects[$projectId] ?? '—'], Row::GROUP);

            foreach ($byProject->groupBy('cost_center_id') as $centreId => $group) {
                $cells = $this->cells($group, $columns);

                foreach ($cells as $key => $value) {
                    $totals[$key] += $value;
                }

                $rows[] = new Row(['project' => $centres[$centreId] ?? '—', ...$cells], Row::DETAIL, ['*' => $this->drillToReport('RPT-03', $filters, ['projects' => [(string) $projectId], 'cost_centers' => [(string) $centreId], 'accounts' => array_map('strval', $all)])], 1);
            }
        }

        $rows[] = new Row(['project' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('project', __('reports.columns.project')),
                Column::amount('cip', __('reports.columns.cip_additions')),
                Column::amount('operating', __('reports.columns.operating_costs')),
                Column::amount('administrative', __('reports.columns.administrative_costs')),
                Column::amount('fixed_assets', __('reports.columns.fixed_assets')),
                Column::amount('total', __('reports.columns.total_cost')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }

    /**
     * @param  Collection<int, \stdClass>  $group
     * @param  array<string, list<int>>  $columns
     * @return array<string, int>
     */
    private function cells(Collection $group, array $columns): array
    {
        $cells = [];

        foreach ($columns as $key => $accounts) {
            $cells[$key] = (int) $group->whereIn('account_id', $accounts)->sum('net');
        }

        $cells['total'] = array_sum($cells);

        return $cells;
    }
}
