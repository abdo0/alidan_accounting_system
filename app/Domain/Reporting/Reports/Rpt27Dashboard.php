<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Dashboard\Kpis;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/** RPT-27 Executive dashboard, as a printable report: every KPI with its drill-down. */
final class Rpt27Dashboard extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly Kpis $kpis)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-27';
    }

    public function permission(): string
    {
        return 'dashboard.view';
    }

    public function filters(): array
    {
        return ['as_at', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = array_map(fn (array $kpi): Row => new Row(
            $kpi['amount'] ? ['kpi' => __('dashboard.kpis.'.$kpi['key']), 'amount' => $kpi['value']] : ['kpi' => __('dashboard.kpis.'.$kpi['key']), 'count' => (string) $kpi['value']],
            Row::DETAIL,
            ['*' => $this->drillToReport($kpi['report'], $filters, $kpi['filters'])],
        ), $this->kpis->all($filters));

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('kpi', __('reports.columns.kpi')),
                Column::amount('amount', __('reports.columns.amount')),
                Column::text('count', __('reports.columns.count')),
            ],
            rows: $rows,
            filters: $this->describe($filters, asAt: true),
        );
    }
}
