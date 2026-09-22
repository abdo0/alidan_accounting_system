<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Result;

/** A report column. Type decides alignment and formatting on screen, in PDF and in Excel. */
final readonly class Column
{
    public const TEXT = 'text';

    public const AMOUNT = 'amount';

    public const DATE = 'date';

    public const NUMBER = 'number';

    public function __construct(
        public string $key,
        public string $label,
        public string $type = self::TEXT,
    ) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, self::TEXT);
    }

    public static function amount(string $key, string $label): self
    {
        return new self($key, $label, self::AMOUNT);
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, self::DATE);
    }
}
