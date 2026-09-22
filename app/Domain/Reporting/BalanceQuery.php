<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use Illuminate\Support\Facades\DB;

/**
 * Reads balances for reporting.
 *
 * gl_balances holds PERIOD MOVEMENTS ONLY and deliberately has no opening-balance
 * column: the year-end opening journal posts through the normal path into period 1, so
 * storing an opening figure as well would double-count it. A balance is therefore the
 * sum of movements over periods 1..N of the fiscal year, computed here.
 */
final class BalanceQuery
{
    /**
     * Net movement per account code, debit positive, for periods 1..N of a fiscal year.
     *
     * Keyed by the account's own code. Roll-up to a parent is done by prefix in
     * sumByPrefixes(), which is the aggregation the standard designed its decimal
     * numbering around.
     *
     * @return array<string, string>
     */
    public function netByAccountCode(int $entityId, int $fiscalYearId, ?int $throughPeriodNo = null): array
    {
        $rows = DB::table('gl_balances as b')
            ->join('accounts as a', 'a.id', '=', 'b.account_id')
            ->join('fiscal_periods as p', 'p.id', '=', 'b.fiscal_period_id')
            ->where('b.entity_id', $entityId)
            ->where('p.fiscal_year_id', $fiscalYearId)
            ->when($throughPeriodNo !== null, fn ($q) => $q->where('p.period_no', '<=', $throughPeriodNo))
            ->groupBy('a.code')
            ->selectRaw('a.code, sum(b.period_debit - b.period_credit) as net')
            ->pluck('net', 'code');

        $out = [];

        foreach ($rows as $code => $net) {
            $out[(string) $code] = (string) $net;
        }

        return $out;
    }

    /**
     * Sum every account whose code begins with one of the given prefixes.
     *
     * A line naming `41` picks up 411..417 and every level beneath it. Leaf balances
     * only ever exist at postable accounts, so summing by prefix cannot double-count a
     * parent and its children.
     *
     * @param  array<string, string>  $netByCode
     * @param  array<int, string>  $prefixes
     */
    public function sumByPrefixes(array $netByCode, array $prefixes): string
    {
        $total = '0';

        foreach ($netByCode as $code => $net) {
            // PHP coerces numeric-string array keys to int, so '183' comes back as 183.
            $code = (string) $code;

            foreach ($prefixes as $prefix) {
                if (str_starts_with($code, $prefix)) {
                    $total = bcadd($total, $net, 4);
                    break;   // a code matching two prefixes must still count once
                }
            }
        }

        return $total;
    }

    /**
     * Net movement per composite cost code (٥٣١-style), for كشف توزيع الاستخدامات.
     *
     * Reads journal_lines rather than gl_balances, because the composite code is a line
     * attribute and is deliberately not part of the balance store's key.
     *
     * @return array<string, array<string, string>> element => centre class => amount
     */
    public function usesByCostCentreClass(int $entityId, int $fiscalYearId, ?int $throughPeriodNo = null): array
    {
        $rows = DB::table('journal_lines as l')
            ->join('fiscal_periods as p', 'p.id', '=', 'l.fiscal_period_id')
            ->where('l.entity_id', $entityId)
            ->whereNotNull('l.posted_at')
            ->whereNotNull('l.cost_account_code')
            ->where('p.fiscal_year_id', $fiscalYearId)
            ->when($throughPeriodNo !== null, fn ($q) => $q->where('p.period_no', '<=', $throughPeriodNo))
            ->groupBy('l.cost_account_code')
            ->selectRaw('l.cost_account_code, sum(l.debit_amount - l.credit_amount) as net')
            ->get();

        $grid = [];

        foreach ($rows as $row) {
            $composite = (string) $row->cost_account_code;   // e.g. 531
            $class = substr($composite, 0, 1);               // 5
            $element = substr($composite, 1);                // 31
            $grid[$element][$class] = (string) $row->net;
        }

        return $grid;
    }
}
