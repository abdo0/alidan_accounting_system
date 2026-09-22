<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Organisation\Company;
use App\Domain\Reporting\Filters\FilterApplier;
use App\Domain\Reporting\Filters\FilterSet;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one base query every report aggregates over: journal_lines of entries in the
 * ledger, for the company, with the filters applied before aggregation (Document B
 * §6: security scope and filters first, then aggregation). No report reads a stored
 * balance, because none exists.
 */
final class LedgerQuery
{
    public function __construct(private readonly FilterApplier $applier) {}

    public function lines(FilterSet $filters, bool $range = true): Builder
    {
        $query = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jh.company_id', Company::current()->id)
            ->whereIn('jh.status', JournalStatus::ledgerValues());

        return $this->applier->apply($query, $filters, $range);
    }

    /**
     * Net (debit - credit) per account, as at a date: everything posted up to and
     * including it.
     *
     * @return array<int, int> account_id => natural balance
     */
    public function balancesAsAt(FilterSet $filters, ?\DateTimeInterface $asAt = null): array
    {
        return $this->lines($filters, range: false)
            ->where('jh.posting_date', '<=', ($asAt ?? $filters->asAt())->format('Y-m-d'))
            ->groupBy('jl.account_id')
            ->selectRaw('jl.account_id, sum(jl.debit) - sum(jl.credit) AS net')
            ->pluck('net', 'account_id')
            ->map(fn ($net): int => (int) $net)
            ->all();
    }

    /**
     * Net movement per account inside the filter range.
     *
     * @return array<int, int>
     */
    public function movements(FilterSet $filters): array
    {
        return $this->lines($filters)
            ->groupBy('jl.account_id')
            ->selectRaw('jl.account_id, sum(jl.debit) - sum(jl.credit) AS net')
            ->pluck('net', 'account_id')
            ->map(fn ($net): int => (int) $net)
            ->all();
    }
}
