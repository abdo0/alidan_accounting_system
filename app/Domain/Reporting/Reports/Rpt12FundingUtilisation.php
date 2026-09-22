<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\FsLine;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * RPT-12 Funding utilisation by use: where the funding went, by final economic
 * use -- Concession CIP, acquisitions, retained fixed assets, operating and
 * administrative costs, personal receivables, sub-advances, recoveries. Read from
 * the debit side of spending entries and grouped by the statement line of the
 * account debited.
 */
class Rpt12FundingUtilisation extends BaseReport
{
    /** Entries that spend funds: payments, settlements, reclassifications, acquisitions. */
    protected const SPENDING_TYPES = ['TT-02', 'TT-05', 'TT-08', 'TT-09', 'TT-10', 'TT-11', 'TT-12', 'TT-13', 'TT-20', 'TT-22', 'TT-25', 'TT-32'];

    public function code(): string
    {
        return 'RPT-12';
    }

    public function filters(): array
    {
        return ['from', 'to', 'shareholders', 'advance_holders', 'projects', 'funding_categories'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $uses = $this->uses($filters);
        $rows = [];

        foreach ($uses as $use) {
            $rows[] = new Row(['use' => $use['label'], 'amount' => $use['amount']], Row::DETAIL, ['*' => $this->drillToReport('RPT-04', $filters, ['accounts' => $use['accounts']])]);
        }

        $rows[] = new Row(['use' => __('reports.total'), 'amount' => (int) $uses->sum('amount')], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('use', __('reports.columns.use')),
                Column::amount('amount', __('reports.columns.amount')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }

    /** @return Collection<int, array{label: string, amount: int, accounts: list<string>}> */
    protected function uses(FilterSet $filters): Collection
    {
        $debits = $this->ledger->lines($filters)
            ->join('transaction_types as tt', 'tt.id', '=', 'jh.transaction_type_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('tt.code', self::SPENDING_TYPES)
            ->where('jl.debit', '>', 0)
            ->groupBy('a.fs_line_code', 'jl.account_id')
            ->selectRaw('a.fs_line_code, jl.account_id, sum(jl.debit) AS amount')
            ->get();

        $lines = FsLine::query()->whereIn('code', $debits->pluck('fs_line_code')->unique())->get()->keyBy('code');

        return $debits->groupBy('fs_line_code')->map(fn (Collection $group, string $code): array => [
            'label' => ($lines[$code] ?? null)?->displayName() ?? $code,
            'amount' => (int) $group->sum('amount'),
            'accounts' => $group->pluck('account_id')->map(fn ($id): string => (string) $id)->values()->all(),
        ])->sortByDesc('amount')->values()->map(fn (array $use): array => [...$use, 'accounts' => array_values($use['accounts'])]);
    }
}
