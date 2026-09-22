<?php

declare(strict_types=1);

namespace App\Support\Spec;

use RuntimeException;

/**
 * Reads the specification data under database/data/shh.
 *
 * Rows come back keyed by the spec's own column headings, so a seeder reads
 * $row['Account Code'] exactly as Document C prints it. A seeder names the columns
 * it relies on; if a re-issued Document C renames or drops one, seeding fails here
 * instead of loading a column of blanks.
 */
final class SpecCsv
{
    /**
     * @param  array<int, string>  $requiredColumns
     * @return list<array<string, string>>
     */
    public static function rows(string $name, array $requiredColumns = []): array
    {
        $path = self::path($name);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open spec data file {$path}.");
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            throw new RuntimeException("Spec data file {$path} is empty.");
        }

        $header = array_map(fn (?string $cell): string => trim((string) $cell), $header);
        $missing = array_diff($requiredColumns, $header);

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Spec data file %s lacks column(s): %s',
                $name,
                implode(', ', $missing),
            ));
        }

        $rows = [];
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            if ($cells === [null]) {
                continue;
            }

            $cells = array_pad(array_map(fn (?string $cell): string => trim((string) $cell), $cells), count($header), '');
            $rows[] = array_combine($header, array_slice($cells, 0, count($header)));
        }

        fclose($handle);

        return $rows;
    }

    public static function path(string $name): string
    {
        $path = dirname(__DIR__, 3).'/database/data/shh/'.$name.'.csv';

        if (! is_file($path)) {
            throw new RuntimeException("Spec data file {$name}.csv does not exist.");
        }

        return $path;
    }

    /**
     * Splits a Document C list cell ("project, cost_center, shareholder_id") into
     * its tokens.
     *
     * @return list<string>
     */
    public static function list(string $cell, string $separator = ','): array
    {
        return array_values(array_filter(
            array_map('trim', explode($separator, $cell)),
            fn (string $token): bool => $token !== '' && $token !== '—',
        ));
    }
}
