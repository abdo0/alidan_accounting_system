<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Counterparty;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RPT-11 Shareholder funding statement (Document B §4.4): per shareholder, the
 * opening claim, cash funding (TT-01), direct payments on the company's behalf
 * (TT-02), repayments and conversions (TT-37), other movements and the closing
 * claim, agreed to the trial balance. Below it, the audit view by actual payment
 * source -- which account physically paid, shown for audit and used nowhere else
 * (Policy 8).
 */
final class Rpt11ShareholderFunding extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-11';
    }

    public function filters(): array
    {
        return ['from', 'to', 'fiscal_year', 'shareholders', 'projects', 'funding_batches'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $from = $filters->from()->toDateString();
        $rows = [];
        $controls = [];

        foreach ($this->sets->shareholderClaims() as $shareholderId => $accounts) {
            if ($filters->ids('shareholders') !== [] && ! in_array($shareholderId, $filters->ids('shareholders'), true)) {
                continue;
            }

            $m = $this->ledger->lines($filters->with(['projects' => $filters->list('projects')]), range: false)
                ->join('transaction_types as tt', 'tt.id', '=', 'jh.transaction_type_id')
                ->whereIn('jl.account_id', $accounts)
                ->where('jh.posting_date', '<=', $filters->to()->toDateString())
                ->selectRaw('coalesce(sum(jl.credit - jl.debit) FILTER (WHERE jh.posting_date < ?), 0) AS opening', [$from])
                ->selectRaw("coalesce(sum(jl.credit) FILTER (WHERE jh.posting_date >= ? AND tt.code = 'TT-01'), 0) AS cash", [$from])
                ->selectRaw("coalesce(sum(jl.credit) FILTER (WHERE jh.posting_date >= ? AND tt.code = 'TT-02'), 0) AS direct", [$from])
                ->selectRaw("coalesce(sum(jl.debit) FILTER (WHERE jh.posting_date >= ? AND tt.code = 'TT-37'), 0) AS repaid", [$from])
                ->selectRaw("coalesce(sum(jl.credit - jl.debit) FILTER (WHERE jh.posting_date >= ? AND tt.code NOT IN ('TT-01','TT-02','TT-37')), 0) AS other", [$from])
                ->first();

            $opening = (int) $m->opening;
            $closing = $opening + (int) $m->cash + (int) $m->direct - (int) $m->repaid + (int) $m->other;
            $name = Counterparty::query()->find($shareholderId)?->displayName();

            $rows[] = new Row([
                'shareholder' => $name,
                'opening' => $opening,
                'cash' => (int) $m->cash,
                'direct' => (int) $m->direct,
                'repaid' => (int) $m->repaid,
                'other' => (int) $m->other,
                'closing' => $closing,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-04', $filters, ['accounts' => array_map('strval', $accounts)])]);

            $tb = -array_sum(array_intersect_key($this->ledger->balancesAsAt(FilterSet::fromArray(['as_at' => $filters->to()->toDateString()] + array_intersect_key($filters->toArray(), ['projects' => 1]))), array_flip($accounts)));
            $controls[] = new Control(__('reports.controls.agrees_to_tb', ['name' => $name]), $closing, $tb);
        }

        $rows[] = new Row(['shareholder' => __('reports.audit_view')], Row::GROUP);

        $sources = $this->ledger->lines($filters)
            ->whereIn('jl.account_id', array_merge(...array_values($this->sets->shareholderClaims()) ?: [[]]))
            ->where('jl.credit', '>', 0)
            ->groupBy('jl.actual_payment_source')
            ->select(['jl.actual_payment_source', DB::raw('sum(jl.credit) AS amount')])
            ->get();

        foreach ($sources as $source) {
            $rows[] = new Row(['shareholder' => $source->actual_payment_source ?? __('reports.not_stated'), 'cash' => (int) $source->amount], Row::DETAIL, [], 1);
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('shareholder', __('reports.columns.shareholder')),
                Column::amount('opening', __('reports.columns.opening')),
                Column::amount('cash', __('reports.columns.cash_funding')),
                Column::amount('direct', __('reports.columns.direct_payments')),
                Column::amount('repaid', __('reports.columns.repaid')),
                Column::amount('other', __('reports.columns.other')),
                Column::amount('closing', __('reports.columns.closing')),
            ],
            rows: $rows,
            controls: $controls,
            filters: $this->describe($filters),
        );
    }
}
