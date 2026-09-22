<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Domain\Revenue\GovernmentShareService;
use App\Models\User;

/**
 * RPT-19 Government share reconciliation: per period, eligible revenue, the rate in
 * force, share due, share recognised, payments and the closing payable, with the
 * due-versus-recognised check. On the migrated ledger every one of these is nil --
 * no revenue has yet been recognised.
 */
final class Rpt19GovernmentShare extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly GovernmentShareService $share, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-19';
    }

    public function filters(): array
    {
        return ['from', 'to', 'fiscal_year', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $project = $filters->ids('projects')[0] ?? null;
        $payable = $this->sets->ofRule('PR-25', 'credit');
        $rows = [];
        $due = 0;
        $recognised = 0;

        $periods = AccountingPeriod::query()
            ->where('starts_on', '<=', $filters->to()->toDateString())
            ->where('ends_on', '>=', $filters->from()->toDateString())
            ->orderBy('starts_on')->get();

        foreach ($periods as $period) {
            $f = $this->share->compute($period, $project);
            $paid = (int) $this->ledger->lines(FilterSet::fromArray(['period' => (string) $period->id] + array_intersect_key($filters->toArray(), ['projects' => 1])))
                ->whereIn('jl.account_id', $payable)->sum('jl.debit');

            if ($f['eligible'] === 0 && $f['recognised'] === 0 && $paid === 0 && $f['excluded'] === 0) {
                continue;
            }

            $due += $f['due'];
            $recognised += $f['recognised'];

            $rows[] = new Row([
                'period' => $period->period_code,
                'eligible' => $f['eligible'],
                'excluded' => $f['excluded'],
                'rate' => $f['rate'],
                'due' => $f['due'],
                'recognised' => $f['recognised'],
                'paid' => $paid,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-04', $filters, ['period' => (string) $period->id])]);
        }

        $closing = -array_sum(array_intersect_key($this->ledger->balancesAsAt($filters, $filters->to()), array_flip($payable)));
        $rows[] = new Row(['period' => __('reports.total'), 'due' => $due, 'recognised' => $recognised, 'paid' => null], Row::TOTAL);
        $rows[] = new Row(['period' => __('reports.revenue.closing_payable'), 'paid' => $closing], Row::SUBTOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('period', __('reports.columns.period')),
                Column::amount('eligible', __('reports.columns.eligible_revenue')),
                Column::amount('excluded', __('reports.columns.excluded_revenue')),
                Column::text('rate', __('reports.columns.rate')),
                Column::amount('due', __('reports.columns.share_due')),
                Column::amount('recognised', __('reports.columns.share_recognised')),
                Column::amount('paid', __('reports.columns.paid')),
            ],
            rows: $rows,
            controls: [new Control(__('reports.revenue.due_vs_recognised'), $due, $recognised)],
            filters: $this->describe($filters),
        );
    }
}
