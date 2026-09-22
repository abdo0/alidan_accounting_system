<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Result;

/**
 * What a report produces. Screen, PDF and Excel all render this same object, so an
 * export always shows exactly what the screen showed (Document B §6).
 */
final readonly class ReportResult
{
    /**
     * @param  list<Column>  $columns
     * @param  list<Row>  $rows
     * @param  list<Control>  $controls
     * @param  array<string, string>  $filters  label => value, printed at the head of every export
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $code,
        public string $title,
        public array $columns,
        public array $rows,
        public array $controls = [],
        public array $filters = [],
        public array $notes = [],
    ) {}

    public function controlsPass(): bool
    {
        foreach ($this->controls as $control) {
            if (! $control->passes()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<Row> */
    public function rowsOfStyle(string $style): array
    {
        return array_values(array_filter($this->rows, fn (Row $row): bool => $row->style === $style));
    }

    public function findRow(string $column, string $value): ?Row
    {
        foreach ($this->rows as $row) {
            if (($row->cells[$column] ?? null) === $value) {
                return $row;
            }
        }

        return null;
    }
}
