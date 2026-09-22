<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

final readonly class RenderedLine
{
    public function __construct(
        public int $sequence,
        public string $label,
        public string $lineType,
        public ?string $current,
        public ?string $prior,
        public int $indentLevel = 0,
        public bool $isBold = false,
        public ?int $analyticalRef = null,
        /** The chart codes this line rolls up, printed in the رقم الدليل column. */
        public ?string $accountCodeLabel = null,
    ) {}

    public function carriesFigure(): bool
    {
        return $this->current !== null;
    }
}
