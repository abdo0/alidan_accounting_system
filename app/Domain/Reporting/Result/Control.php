<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Result;

/**
 * A control printed with a report: what it proves, the two sides, the difference.
 * A control is reported at its true value and never forced (VR-56, Document B §2.6).
 */
final readonly class Control
{
    public function __construct(
        public string $label,
        public int $left,
        public int $right,
    ) {}

    public function difference(): int
    {
        return $this->left - $this->right;
    }

    public function passes(): bool
    {
        return $this->difference() === 0;
    }
}
