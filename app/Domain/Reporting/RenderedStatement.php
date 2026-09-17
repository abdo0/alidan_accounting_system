<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

/**
 * A statement resolved against a period: the definition plus one row per line, with the
 * current and prior-year figures the standard's column frame requires.
 */
final readonly class RenderedStatement
{
    /** @param  list<RenderedLine>  $lines */
    public function __construct(
        public StatementDefinition $definition,
        public array $lines,
        public string $periodLabel,
        public ?string $awaitingModule = null,
    ) {}

    public function isAwaitingData(): bool
    {
        return $this->awaitingModule !== null;
    }

    /** The figure on a given line sequence — used by tests and by reconciliations. */
    public function figure(int $sequence): ?string
    {
        foreach ($this->lines as $line) {
            if ($line->sequence === $sequence) {
                return $line->current;
            }
        }

        return null;
    }

    public function total(): ?string
    {
        $totals = array_filter($this->lines, fn (RenderedLine $l): bool => $l->lineType === StatementLine::TOTAL);

        return $totals === [] ? null : end($totals)->current;
    }
}
