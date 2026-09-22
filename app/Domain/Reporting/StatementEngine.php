<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Reporting\Filters\FilterSet;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * generate(statement, filters) of Document B §4.6.
 *
 *   1  resolve the range from the filters
 *   2  aggregate posted journal_lines per account, filters applied first
 *   3  join the report mappings effective at the reporting date
 *   4  line value = Σ natural balance of its accounts × the line's sign
 *   5  subtotals bottom-up via subtotal_of
 *   6  run the statement's control
 *   7  attach the account set of every line, for drill-down
 *
 * Subtotals add their children's natural (debit-positive) balances and then apply
 * their own sign. Adding presented figures instead would give net retained revenue
 * as revenue PLUS the government share; adding natural balances gives revenue
 * minus the share, which is what PL-030 means.
 *
 * No step reads a stored balance, and no account list lives in this class.
 */
final class StatementEngine
{
    public function __construct(private readonly LedgerQuery $ledger) {}

    /**
     * @return Collection<int, array{line: FsLine, natural: int, value: int, accounts: list<int>}>
     */
    public function generate(string $statement, FilterSet $filters): Collection
    {
        $asAt = $filters->asAt();
        $lines = $this->lines($statement, $asAt);
        $mapping = $this->mapping($statement, $asAt);

        $balances = $statement === 'SFP'
            ? $this->ledger->balancesAsAt($filters)
            : $this->ledger->movements($filters);

        $naturals = [];
        $accountsOf = [];

        foreach ($lines as $line) {
            if ($line->line_type === 'accounts') {
                $accountsOf[$line->code] = array_keys(array_filter($mapping, fn (string $code): bool => $code === $line->code));
                $naturals[$line->code] = array_sum(array_map(fn (int $id): int => $balances[$id] ?? 0, $accountsOf[$line->code]));
            }
        }

        foreach ($lines->where('line_type', 'computed') as $line) {
            // SFP-E-250: the current result, computed from the P&L through the same
            // date -- 310040 is used only after the year-end transfer.
            $source = $this->generate('PL', FilterSet::fromArray(['from' => '1900-01-01', 'to' => $asAt->toDateString()] + array_diff_key($filters->toArray(), array_flip(['from', 'to', 'as_at', 'period', 'fiscal_year']))));
            $naturals[$line->code] = $source->firstWhere(fn (array $row): bool => $row['line']->code === $line->computed_from)['natural'] ?? 0;
            $accountsOf[$line->code] = $source->firstWhere(fn (array $row): bool => $row['line']->code === $line->computed_from)['accounts'] ?? [];
        }

        $resolve = function (string $code) use (&$resolve, &$naturals, &$accountsOf, $lines): int {
            if (array_key_exists($code, $naturals)) {
                return $naturals[$code];
            }

            $children = $lines->where('subtotal_of', $code);
            $naturals[$code] = (int) $children->sum(fn (FsLine $child): int => $resolve($child->code));
            $accountsOf[$code] = $children->flatMap(fn (FsLine $child): array => $accountsOf[$child->code] ?? [])->values()->all();

            return $naturals[$code];
        };

        foreach ($lines as $line) {
            if ($line->line_type === 'subtotal') {
                $resolve($line->code);
            }
        }

        return $lines->map(function (FsLine $line) use ($naturals, $accountsOf, $lines): array {
            $natural = match ($line->line_type) {
                'control' => $this->control($line, $naturals, $lines),
                default => $naturals[$line->code] ?? 0,
            };

            return [
                'line' => $line,
                'natural' => $natural,
                'value' => $line->line_type === 'control' ? $natural : $natural * $line->sign,
                'accounts' => array_values($accountsOf[$line->code] ?? []),
            ];
        })->values();
    }

    /**
     * SFP-CHK: Assets − (Liabilities + Equity) = 0. In natural balances every side is
     * signed already, so the check is that the natural totals sum to nil.
     *
     * @param  array<string, int>  $naturals
     * @param  Collection<int, FsLine>  $lines
     */
    private function control(FsLine $line, array $naturals, Collection $lines): int
    {
        $totals = $lines->where('statement', $line->statement)->whereNull('subtotal_of')->where('line_type', 'subtotal');

        return (int) $totals->sum(fn (FsLine $total): int => $naturals[$total->code] ?? 0);
    }

    /** @return Collection<int, FsLine> */
    private function lines(string $statement, DateTimeInterface $asAt): Collection
    {
        $day = $asAt->format('Y-m-d');

        return FsLine::query()
            ->where('statement', $statement)
            ->whereDate('effective_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $day))
            ->orderBy('display_order')
            ->get();
    }

    /** @return array<int, string> account_id => fs line code */
    private function mapping(string $statement, DateTimeInterface $asAt): array
    {
        $day = $asAt->format('Y-m-d');

        return ReportMapping::query()
            ->where('statement', $statement)
            ->whereDate('effective_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $day))
            ->pluck('fs_line_code', 'account_id')
            ->all();
    }
}
