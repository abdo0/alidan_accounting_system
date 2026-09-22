<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\Project;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-18 Concession CIP register (M12): per CIP account and project -- opening,
 * additions, transfers out, closing -- with the handover and asset classification
 * carried from the lines. Transfers out stay nil until the commercial operation
 * date is set (VR-39).
 */
final class Rpt18CipRegister extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-18';
    }

    public function filters(): array
    {
        return ['from', 'to', 'projects', 'cost_centers', 'accounts', 'handover', 'asset_classes'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $from = $filters->from()->toDateString();

        $data = $this->ledger->lines($filters, range: false)
            ->whereIn('jl.account_id', $this->sets->onLine('SFP-A-040'))
            ->where('jh.posting_date', '<=', $filters->to()->toDateString())
            ->groupBy('jl.account_id', 'jl.project_id')
            ->selectRaw('jl.account_id, jl.project_id')
            ->selectRaw('coalesce(sum(jl.debit - jl.credit) FILTER (WHERE jh.posting_date < ?), 0) AS opening', [$from])
            ->selectRaw('coalesce(sum(jl.debit) FILTER (WHERE jh.posting_date >= ?), 0) AS additions', [$from])
            ->selectRaw('coalesce(sum(jl.credit) FILTER (WHERE jh.posting_date >= ?), 0) AS transfers', [$from])
            ->selectRaw("string_agg(DISTINCT jl.handover_req, ', ') AS handover")
            ->selectRaw("string_agg(DISTINCT jl.asset_class, ', ') AS asset_class")
            ->get();

        $accounts = Account::query()->whereIn('id', $data->pluck('account_id'))->get()->keyBy('id');
        $projects = Project::query()->pluck('code', 'id');
        $rows = [];
        $totals = array_fill_keys(['opening', 'additions', 'transfers', 'closing'], 0);

        foreach ($data->sortBy(fn ($r): string => $accounts[$r->account_id]->code.$projects[$r->project_id]) as $r) {
            $cells = [
                'opening' => (int) $r->opening,
                'additions' => (int) $r->additions,
                'transfers' => (int) $r->transfers,
                'closing' => (int) $r->opening + (int) $r->additions - (int) $r->transfers,
            ];

            foreach ($cells as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = new Row([
                'account' => $accounts[$r->account_id]->label(),
                'project' => $projects[$r->project_id],
                ...$cells,
                'handover' => $r->handover,
                'asset_class' => $r->asset_class,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => [(string) $r->account_id], 'projects' => [(string) $r->project_id]])]);
        }

        $rows[] = new Row(['account' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('account', __('reports.columns.account')),
                Column::text('project', __('reports.columns.project')),
                Column::amount('opening', __('reports.columns.opening')),
                Column::amount('additions', __('reports.columns.additions')),
                Column::amount('transfers', __('reports.columns.transfers_out')),
                Column::amount('closing', __('reports.columns.closing')),
                Column::text('handover', __('reports.columns.handover')),
                Column::text('asset_class', __('reports.columns.asset_class')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
