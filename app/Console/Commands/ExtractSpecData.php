<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Turns Document C (the data dictionary workbook) into one committed CSV per tab.
 *
 * Seeders read the CSVs, never the workbook: a re-issued Document C is extracted
 * again and the diff of database/data/shh is the review of what changed. Every tab
 * carries three title rows (English, Arabic, description) above its header row, and
 * the header is kept verbatim so a seeder names a column exactly as the spec does.
 */
#[Signature('shh:spec:extract {xlsx=docs/specification/SHH-01_Document_C_Data_Dictionary_and_Accounting_Mapping.xlsx} {--out=database/data/shh}')]
#[Description('Extract Document C tabs into database/data/shh CSV files')]
class ExtractSpecData extends Command
{
    private const TITLE_ROWS = 3;

    /** Tabs that are prose, not tables. */
    private const PROSE_TABS = ['00_README'];

    public function handle(): int
    {
        $source = base_path((string) $this->argument('xlsx'));
        $outDir = base_path((string) $this->option('out'));

        if (! is_file($source)) {
            $this->error("Workbook not found: {$source}");

            return self::FAILURE;
        }

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $reader = new Reader;
        $reader->open($source);

        $manifest = [
            'source' => basename($source),
            'sha256' => hash_file('sha256', $source),
            'tabs' => [],
        ];

        foreach ($reader->getSheetIterator() as $sheet) {
            $name = $sheet->getName();

            if (in_array($name, self::PROSE_TABS, true)) {
                continue;
            }

            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map($this->cellToString(...), $row->toArray());
            }

            $header = $this->trimTrailingBlanks($rows[self::TITLE_ROWS] ?? []);
            $width = count($header);
            $data = [];

            foreach (array_slice($rows, self::TITLE_ROWS + 1) as $row) {
                $row = array_slice(array_pad($row, $width, ''), 0, $width);

                if (implode('', $row) === '') {
                    continue;
                }

                $data[] = $row;
            }

            $file = $outDir.'/'.$name.'.csv';
            $handle = fopen($file, 'wb');
            fputcsv($handle, $header, escape: '');
            foreach ($data as $row) {
                fputcsv($handle, $row, escape: '');
            }
            fclose($handle);

            $manifest['tabs'][$name] = ['rows' => count($data), 'columns' => $width];
            $this->line(sprintf('%-24s %4d rows', $name, count($data)));
        }

        $reader->close();

        file_put_contents(
            $outDir.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        return self::SUCCESS;
    }

    private function cellToString(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => trim(str_replace(["\r\n", "\r"], "\n", (string) $value)),
        };
    }

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function trimTrailingBlanks(array $row): array
    {
        while ($row !== [] && end($row) === '') {
            array_pop($row);
        }

        return $row;
    }
}
