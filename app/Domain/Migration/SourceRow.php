<?php

declare(strict_types=1);

namespace App\Domain\Migration;

/**
 * One row of the authoritative journal ledger, read column by column as the
 * layout of Document C tab 11 names them (database/data/shh/file1_layout.csv).
 * Values are kept exactly as the source holds them.
 */
final readonly class SourceRow
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public int $rowNumber,
        public array $fields,
    ) {}

    public function get(string $field): string
    {
        return trim($this->fields[$field] ?? '');
    }

    public function amount(string $field): ?int
    {
        $value = str_replace([',', ' '], '', $this->get($field));

        return $value === '' ? null : (int) round((float) $value);
    }

    /** True where the source value was not a whole number: such rows never load. */
    public function isFractional(string $field): bool
    {
        $value = str_replace([',', ' '], '', $this->get($field));

        return $value !== '' && is_numeric($value) && floor((float) $value) !== (float) $value;
    }
}
