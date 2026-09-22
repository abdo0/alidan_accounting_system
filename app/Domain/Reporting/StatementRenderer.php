<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Organisation\FiscalYear;
use RuntimeException;

/**
 * Resolves a statement definition against a fiscal year.
 *
 * Roll-up is by code prefix, which is what the standard designed its decimal numbering
 * for: "تجميع البيانات تلقائياً وفق تبويبات الدليل". A line naming 41 gathers 411..417
 * and everything beneath without needing to enumerate them.
 */
final class StatementRenderer
{
    public function __construct(private readonly BalanceQuery $balances) {}

    public function render(
        StatementDefinition $definition,
        int $entityId,
        FiscalYear $fiscalYear,
        ?int $throughPeriodNo = null,
        ?FiscalYear $priorYear = null,
    ): RenderedStatement {
        $current = $this->balances->netByAccountCode($entityId, $fiscalYear->id, $throughPeriodNo);
        $prior = $priorYear === null
            ? []
            : $this->balances->netByAccountCode($entityId, $priorYear->id);

        $computedCurrent = [];
        $computedPrior = [];
        $lines = [];

        foreach ($definition->lines as $line) {
            [$cur, $pri] = $this->resolve($line, $current, $prior, $computedCurrent, $computedPrior);

            $computedCurrent[$line->sequence] = $cur;
            $computedPrior[$line->sequence] = $pri;

            $lines[] = new RenderedLine(
                sequence: $line->sequence,
                label: $line->label(),
                lineType: $line->line_type,
                current: $cur,
                prior: $pri,
                indentLevel: $line->indent_level,
                isBold: $line->is_bold || in_array($line->line_type, [StatementLine::TOTAL, StatementLine::SUBTOTAL], true),
                analyticalRef: $line->analytical_ref,
                accountCodeLabel: $this->codeLabel($line),
            );
        }

        return new RenderedStatement(
            definition: $definition,
            lines: $lines,
            periodLabel: $this->periodLabel($fiscalYear, $throughPeriodNo),
            awaitingModule: $definition->awaiting_module,
        );
    }

    /**
     * @param  array<string, string>  $current
     * @param  array<string, string>  $prior
     * @param  array<int, string|null>  $computedCurrent
     * @param  array<int, string|null>  $computedPrior
     * @return array{string|null, string|null}
     */
    private function resolve(
        StatementLine $line,
        array $current,
        array $prior,
        array $computedCurrent,
        array $computedPrior,
    ): array {
        return match ($line->line_type) {
            StatementLine::ACCOUNTS => [
                $this->signed($this->balances->sumByPrefixes($current, $line->account_codes ?? []), $line->sign),
                $this->signed($this->balances->sumByPrefixes($prior, $line->account_codes ?? []), $line->sign),
            ],
            StatementLine::FORMULA, StatementLine::SUBTOTAL, StatementLine::TOTAL => [
                $this->evaluate($line->formula, $computedCurrent),
                $this->evaluate($line->formula, $computedPrior),
            ],
            default => [null, null],
        };
    }

    private function signed(string $value, int $sign): string
    {
        return $sign === -1 ? bcmul($value, '-1', 4) : $value;
    }

    /**
     * Evaluates a line formula such as `L10 - L20 + L30`.
     *
     * Deliberately a tiny hand-written parser rather than eval(): these definitions are
     * data, and data that can be edited must never become executable.
     *
     * @param  array<int, string|null>  $computed
     */
    private function evaluate(?string $formula, array $computed): ?string
    {
        if ($formula === null || trim($formula) === '') {
            return null;
        }

        if (preg_match_all('/([+-]?)\s*L(\d+)/', $formula, $matches, PREG_SET_ORDER) === 0) {
            throw new RuntimeException("Unparseable statement formula: [{$formula}]");
        }

        $total = '0';

        foreach ($matches as $match) {
            $operator = $match[1] === '-' ? '-' : '+';
            $sequence = (int) $match[2];
            $value = $computed[$sequence] ?? '0';

            $total = $operator === '-'
                ? bcsub($total, $value, 4)
                : bcadd($total, $value, 4);
        }

        return $total;
    }

    private function codeLabel(StatementLine $line): ?string
    {
        $codes = $line->account_codes ?? [];

        return $codes === [] ? null : implode('، ', $codes);
    }

    private function periodLabel(FiscalYear $fiscalYear, ?int $throughPeriodNo): string
    {
        if ($throughPeriodNo === null) {
            return __('accounting.statement.for_year_ended', [
                'date' => $fiscalYear->ends_on->format('d/m/Y'),
            ]);
        }

        return __('accounting.statement.for_period_ended', [
            'period' => (string) $throughPeriodNo,
            'year' => $fiscalYear->code,
        ]);
    }
}
