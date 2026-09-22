<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Account;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * RPT-04 Trial balance (Document B §4.6): opening Dr/Cr (postings before the range),
 * period Dr/Cr (postings inside it), closing Dr/Cr (net), with group subtotals and
 * the three balance proofs printed on every run. A balancing plug is never
 * generated (VR-56).
 */
final class Rpt04TrialBalance extends BaseReport
{
    public function code(): string
    {
        return 'RPT-04';
    }

    public function filters(): array
    {
        return ['from', 'to', 'fiscal_year', 'period', 'projects', 'cost_centers', 'resp_centers', 'accounts', 'account_groups'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $from = $filters->from()->toDateString();

        $sums = $this->ledger->lines($filters, range: false)
            ->where('jh.posting_date', '<=', $filters->to()->toDateString())
            ->groupBy('jl.account_id')
            ->selectRaw('jl.account_id')
            ->selectRaw('coalesce(sum(jl.debit) FILTER (WHERE jh.posting_date < ?), 0) AS open_dr', [$from])
            ->selectRaw('coalesce(sum(jl.credit) FILTER (WHERE jh.posting_date < ?), 0) AS open_cr', [$from])
            ->selectRaw('coalesce(sum(jl.debit) FILTER (WHERE jh.posting_date >= ?), 0) AS period_dr', [$from])
            ->selectRaw('coalesce(sum(jl.credit) FILTER (WHERE jh.posting_date >= ?), 0) AS period_cr', [$from])
            ->get()
            ->keyBy('account_id');

        $accounts = $this->accounts();
        $rows = [];
        $totals = array_fill_keys(['open_dr', 'open_cr', 'period_dr', 'period_cr', 'close_dr', 'close_cr'], 0);

        foreach ($accounts->where('is_group', true) as $group) {
            $members = $accounts->filter(fn (Account $a): bool => ! $a->is_group && $this->groupOf($a, $accounts)?->id === $group->id && $sums->has($a->id));

            if ($members->isEmpty()) {
                continue;
            }

            $rows[] = new Row(['code' => $group->code, 'name' => $this->name($group)], Row::GROUP);
            $groupTotals = array_fill_keys(array_keys($totals), 0);

            foreach ($members as $account) {
                $s = $sums[$account->id];
                $figures = $this->figures((int) $s->open_dr, (int) $s->open_cr, (int) $s->period_dr, (int) $s->period_cr);

                foreach ($figures as $key => $value) {
                    $groupTotals[$key] += $value;
                    $totals[$key] += $value;
                }

                $rows[] = new Row(
                    ['code' => $account->code, 'name' => $this->name($account), ...$figures],
                    Row::DETAIL,
                    ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => [(string) $account->id]])],
                    1,
                );
            }

            $rows[] = new Row(['code' => '', 'name' => __('reports.subtotal', ['group' => $this->name($group)]), ...$groupTotals], Row::SUBTOTAL);
        }

        $rows[] = new Row(['code' => '', 'name' => __('reports.total'), ...$totals], Row::TOTAL);

        $journalDebits = (int) $this->ledger->lines($filters)->sum('jl.debit');

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('code', __('reports.columns.code')),
                Column::text('name', __('reports.columns.account')),
                Column::amount('open_dr', __('reports.columns.opening_dr')),
                Column::amount('open_cr', __('reports.columns.opening_cr')),
                Column::amount('period_dr', __('reports.columns.period_dr')),
                Column::amount('period_cr', __('reports.columns.period_cr')),
                Column::amount('close_dr', __('reports.columns.closing_dr')),
                Column::amount('close_cr', __('reports.columns.closing_cr')),
            ],
            rows: $rows,
            controls: [
                new Control(__('reports.controls.movement_balances'), $totals['period_dr'], $totals['period_cr']),
                new Control(__('reports.controls.balances_balance'), $totals['close_dr'], $totals['close_cr']),
                new Control(__('reports.controls.agrees_to_journal'), $totals['period_dr'], $journalDebits),
            ],
            filters: $this->describe($filters),
        );
    }

    /** @return array{open_dr: int, open_cr: int, period_dr: int, period_cr: int, close_dr: int, close_cr: int} */
    private function figures(int $openDr, int $openCr, int $periodDr, int $periodCr): array
    {
        $opening = $openDr - $openCr;
        $closing = $opening + $periodDr - $periodCr;

        return [
            'open_dr' => max($opening, 0),
            'open_cr' => max(-$opening, 0),
            'period_dr' => $periodDr,
            'period_cr' => $periodCr,
            'close_dr' => max($closing, 0),
            'close_cr' => max(-$closing, 0),
        ];
    }

    /** @param  Collection<int, Account>  $accounts */
    private function groupOf(Account $account, Collection $accounts): ?Account
    {
        $current = $account;

        while ($current !== null && ! $current->is_group) {
            $current = $current->parent_id === null ? null : $accounts->get($current->parent_id);
        }

        return $current;
    }
}
