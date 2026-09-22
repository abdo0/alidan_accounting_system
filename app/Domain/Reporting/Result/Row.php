<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Result;

/**
 * A report row. Every aggregated figure carries a drill-down target: statement line
 * -> account set -> account ledger -> journal entry -> document (Document B §6).
 * A target is data -- ['report' => 'RPT-05', 'filters' => [...]] or
 * ['journal' => id] -- and the presentation layer turns it into a link.
 */
final readonly class Row
{
    public const DETAIL = 'detail';

    public const GROUP = 'group';

    public const SUBTOTAL = 'subtotal';

    public const TOTAL = 'total';

    /**
     * @param  array<string, scalar|null>  $cells
     * @param  array<string, array<string, mixed>>  $drill  column key (or '*' for the whole row) => target
     */
    public function __construct(
        public array $cells,
        public string $style = self::DETAIL,
        public array $drill = [],
        public int $indent = 0,
    ) {}

    /** @return array<string, mixed>|null */
    public function drillFor(string $column): ?array
    {
        return $this->drill[$column] ?? $this->drill['*'] ?? null;
    }
}
