<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Export;

use App\Domain\Organisation\Company;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row as ResultRow;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel export of a report result (Document B §6, §10). The sheet renders the same
 * rows as the screen, with the filter set printed above the table so an exported
 * report is self-describing. Figures stay numeric cells, formatted with thousands
 * separators, negatives in parentheses and zero as a dash; the sheet reads
 * right-to-left in Arabic.
 */
final class ExcelExporter
{
    private const AMOUNT_FORMAT = '#,##0;(#,##0);"-"';

    public function export(ReportResult $result, string $path, string $user): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(substr(preg_replace('/[^A-Za-z0-9 -]/', '', $result->code) ?: 'Report', 0, 31));
        $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft(app()->getLocale() === 'ar')->setFreezeRow(7 + count($result->filters)));

        $bold = (new Style)->setFontBold();
        $amount = (new Style)->setFormat(self::AMOUNT_FORMAT);
        $boldAmount = (new Style)->setFontBold()->setFormat(self::AMOUNT_FORMAT);

        $company = Company::current();
        $writer->addRow(Row::fromValues([$company->displayName().' — '.$company->code], $bold));
        $writer->addRow(Row::fromValues([$result->title], $bold));
        $writer->addRow(Row::fromValues([__('reports.generated', ['at' => now()->toDateTimeString(), 'user' => $user])]));

        foreach ($result->filters as $label => $value) {
            $writer->addRow(Row::fromValues([$label, $value]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(array_map(fn (Column $c): string => $c->label, $result->columns), $bold));

        foreach ($result->rows as $row) {
            $emphasis = in_array($row->style, [ResultRow::GROUP, ResultRow::SUBTOTAL, ResultRow::TOTAL], true);

            $cells = array_map(function (Column $column) use ($row, $amount, $boldAmount, $emphasis): Cell {
                $value = $row->cells[$column->key] ?? null;

                if ($column->type === Column::AMOUNT && is_numeric($value)) {
                    return Cell::fromValue((int) $value, $emphasis ? $boldAmount : $amount);
                }

                return Cell::fromValue($value === null ? '' : (string) $value);
            }, $result->columns);

            $writer->addRow(new Row($cells, $emphasis ? $bold : null));
        }

        if ($result->controls !== []) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([__('reports.controls_title')], $bold));

            foreach ($result->controls as $control) {
                $writer->addRow(new Row([
                    Cell::fromValue($control->label),
                    Cell::fromValue($control->left, $amount),
                    Cell::fromValue($control->right, $amount),
                    Cell::fromValue($control->difference(), $amount),
                    Cell::fromValue($control->passes() ? __('reports.passes') : __('reports.fails')),
                ]));
            }
        }

        foreach ($result->notes as $note) {
            $writer->addRow(Row::fromValues([$note]));
        }

        $writer->close();
    }
}
