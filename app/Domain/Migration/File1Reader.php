<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use App\Support\Spec\SpecCsv;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/**
 * Reads the journal ledger of the authoritative workbook
 * (قيود شمس الهيلان - مصحح تمويل الراشدية.xlsx). Data starts at row 4 (MIG-11) and
 * columns are mapped by letter from file1_layout.csv, so a layout correction is a
 * data change, not a code change.
 */
final class File1Reader
{
    public const FIRST_DATA_ROW = 4;

    /** @return list<SourceRow> */
    public function rows(string $path, ?string $sheetName = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $layout = [];
        foreach (SpecCsv::rows('file1_layout', ['Column', 'Field']) as $row) {
            $layout[self::columnIndex($row['Column'])] = $row['Field'];
        }

        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $matches = $sheetName === null
                ? preg_match('/journal|القيود|قيود/iu', $sheet->getName()) === 1 || $sheet->getIndex() === 0
                : $sheet->getName() === $sheetName;

            if (! $matches) {
                continue;
            }

            $number = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $number++;

                if ($number < self::FIRST_DATA_ROW) {
                    continue;
                }

                $cells = $row->toArray();
                $fields = [];

                foreach ($layout as $index => $field) {
                    $value = $cells[$index] ?? '';
                    $fields[$field] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : trim((string) $value);
                }

                if (($fields['jv_no'] ?? '') === '') {
                    continue;
                }

                $rows[] = new SourceRow($number, $fields);
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    public static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
