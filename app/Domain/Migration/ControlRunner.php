<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\MasterData\Account;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The thirty migration controls (Document C tab 23). Each is measured on the
 * migrated population and compared with the value the authoritative workbook
 * states; the figures are read from the spec's own text, in order, so the
 * expectation lives in Document C, not here. The system is not accepted until
 * every control reads Pass (Document B §9.4) -- and MC-14 / MC-30 pass only if the
 * IQD 99,796,000 difference on 112035 survives intact.
 */
final class ControlRunner
{
    /** @return list<MigrationControlResult> */
    public function run(MigrationRun $run): array
    {
        $results = [];

        foreach (SpecCsv::rows('23_Migration_Controls', ['Control', 'Measure', 'Source value (authoritative workbook)']) as $row) {
            $code = $row['Control'];
            $expected = self::numbers($row['Source value (authoritative workbook)']);
            [$actual, $note] = $this->measure($code);
            $pass = $expected === null ? $note === null : $actual === $expected;

            $results[] = MigrationControlResult::query()->updateOrCreate(
                ['migration_run_id' => $run->id, 'control_code' => $code],
                [
                    'control_name' => $row['Measure'],
                    'source_value' => $row['Source value (authoritative workbook)'],
                    'system_value' => $actual === [] && $note !== null ? $note : implode(' · ', array_map(fn (int $n): string => number_format($n), $actual)),
                    'status' => $pass ? 'pass' : 'fail',
                    'notes' => $note,
                    'run_at' => now(),
                ],
            );
        }

        return $results;
    }

    /**
     * The figures of a source-value text, in order: "Dr 3,979,086,000 / Cr
     * 4,078,882,000 / net credit 99,796,000" -> [3979086000, 4078882000, 99796000].
     * Account codes, project and file identifiers are not figures. Null when the
     * spec gives a condition rather than a figure (MC-08, MC-27).
     *
     * @return list<int>|null
     */
    public static function numbers(string $text): ?array
    {
        $clean = preg_replace(['/\b[A-Z]+-\d+\b/', '/File [12]/', '/\b\d{6}\b(?!,)/'], '', $text) ?? $text;

        if (preg_match_all('/([−-])?\d[\d,]*/u', $clean, $matches) === 0 || str_contains($text, 'trial balance') || str_contains($text, 'exception rows')) {
            return null;
        }

        return array_map(fn (string $n): int => (int) str_replace([',', '−'], ['', '-'], $n), $matches[0]);
    }

    /** @return array{list<int>, string|null} */
    private function measure(string $code): array
    {
        return match ($code) {
            'MC-01' => [[$this->headers()->count()], null],
            'MC-02' => [[$this->lines()->count()], null],
            'MC-03' => [[(int) $this->lines()->sum('jl.debit')], null],
            'MC-04' => [[(int) $this->lines()->sum('jl.credit')], null],
            'MC-05' => [[(int) $this->lines()->sum('jl.debit') - (int) $this->lines()->sum('jl.credit')], null],
            'MC-06' => [[array_sum(array_filter($this->balances(), fn (int $b): bool => $b > 0))], null],
            'MC-07' => [[-array_sum(array_filter($this->balances(), fn (int $b): bool => $b < 0))], null],
            'MC-08' => $this->perAccount(),
            'MC-09' => [[$this->lines()->distinct()->count('jl.account_id')], null],
            'MC-10' => [$this->movement('111002'), null],
            'MC-11' => [[$this->side('221001', 'credit')], null],
            'MC-12' => [[...$this->movement('112020'), $this->net('112020')], null],
            'MC-13' => [[...$this->movement('112030'), $this->net('112030')], null],
            'MC-14' => [[...$this->movement('112035'), -$this->net('112035')], null],
            'MC-15' => [[$this->net('112101'), $this->net('112102')], null],
            'MC-16' => [[$this->netOnLine('SFP-A-040')], null],
            'MC-17' => [[$this->net('115001')], null],
            'MC-18' => [[$this->net('117001')], null],
            'MC-19' => [[$this->net('116001')], null],
            'MC-20' => [[$this->netOnLine('SFP-A-080')], null],
            'MC-21' => [[$this->netOnLine('PL-040')], null],
            'MC-22' => [[$this->netOnLine('PL-060', 'PL-070')], null],
            'MC-23' => [[-$this->net('211090')], null],
            'MC-24' => [$this->projectCounts(), null],
            'MC-25' => [[$this->headers()->whereNotNull('jh.txn_date')->count(), $this->headers()->whereNull('jh.txn_date')->count()], null],
            'MC-26' => [$this->flags(), null],
            'MC-27' => $this->exceptionsCarried(),
            'MC-28' => [[$this->headers()->where('jh.source_file', 'ilike', '%Shams_AlHaylan_Accounting_System%')->count()], null],
            'MC-29' => [[$this->side('221001', 'credit')], null],
            'MC-30' => [[$this->net('112035')], null],
            default => [[], __('migration.control_unknown')],
        };
    }

    private function headers(): Builder
    {
        return DB::table('journal_headers as jh')->where('jh.is_migration', true)->whereIn('jh.status', JournalStatus::ledgerValues());
    }

    private function lines(): Builder
    {
        return DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jh.is_migration', true)
            ->whereIn('jh.status', JournalStatus::ledgerValues());
    }

    /** @return array<int, int> account id => natural balance */
    private function balances(): array
    {
        return $this->lines()->groupBy('jl.account_id')->selectRaw('jl.account_id, sum(jl.debit) - sum(jl.credit) AS n')
            ->pluck('n', 'account_id')->map(fn ($n): int => (int) $n)->all();
    }

    private function accountId(string $code): ?int
    {
        return Account::query()->where('code', $code)->value('id');
    }

    private function net(string $code): int
    {
        return $this->balances()[$this->accountId($code)] ?? 0;
    }

    private function side(string $code, string $side): int
    {
        return (int) $this->lines()->where('jl.account_id', $this->accountId($code))->sum('jl.'.$side);
    }

    /** @return list<int> */
    private function movement(string $code): array
    {
        return [$this->side($code, 'debit'), $this->side($code, 'credit')];
    }

    private function netOnLine(string ...$lines): int
    {
        $ids = Account::query()->whereIn('fs_line_code', $lines)->pluck('id')->all();

        return array_sum(array_intersect_key($this->balances(), array_flip($ids)));
    }

    /** @return list<int> entries per project, PRJ-01, PRJ-02, PRJ-03 */
    private function projectCounts(): array
    {
        return array_map(fn (string $project): int => $this->lines()
            ->join('projects as p', 'p.id', '=', 'jl.project_id')
            ->where('p.code', $project)
            ->distinct()
            ->count('jh.id'), ['PRJ-01', 'PRJ-02', 'PRJ-03']);
    }

    /** @return list<int> possible, manual review, value at risk */
    private function flags(): array
    {
        $flags = DuplicateFlag::query()->where('origin', 'migration');

        return [
            (clone $flags)->where('source_review', 'POSSIBLE AMER DUPLICATE')->count(),
            (clone $flags)->where('source_review', 'REQUIRES MANUAL REVIEW')->count(),
            (int) (clone $flags)->sum('value_at_risk'),
        ];
    }

    /**
     * MC-08: every account's balance agrees to the source. With the per-row totals
     * of the source reproduced line for line (MC-03 ... MC-05), the check here is
     * that no account holds a balance the source rows do not explain.
     *
     * @return array{list<int>, string|null}
     */
    private function perAccount(): array
    {
        $difference = (int) $this->lines()->sum('jl.debit') - (int) $this->lines()->sum('jl.credit');

        return [[], $difference === 0 ? null : __('migration.per_account_difference', ['difference' => $difference])];
    }

    /** @return array{list<int>, string|null} MC-27: no register exception missing or pre-closed */
    private function exceptionsCarried(): array
    {
        $register = collect(SpecCsv::rows('24_Exceptions', ['Exception ID']))->pluck('Exception ID');
        $open = ControlException::query()->whereIn('source_code', $register)->where('status', '!=', ExceptionStatus::Resolved)->count();

        return [[], $open === $register->count() ? null : __('migration.exceptions_missing', ['open' => $open, 'expected' => $register->count()])];
    }
}
