<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Domain\Reporting\StatementEngine;
use App\Models\User;

/**
 * A mapping-driven financial statement. Every line drills to the trial balance of
 * the accounts behind it.
 */
abstract class StatementReport extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly StatementEngine $engine)
    {
        parent::__construct($ledger);
    }

    abstract protected function statement(): string;

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = [];
        $controls = [];

        foreach ($this->engine->generate($this->statement(), $filters) as $item) {
            $line = $item['line'];

            if ($line->line_type === 'control') {
                $controls[] = new Control($line->displayName(), $item['value'], 0);

                continue;
            }

            $rows[] = new Row(
                ['code' => $line->code, 'line' => $line->displayName(), 'value' => $item['value']],
                $line->line_type === 'subtotal' ? ($line->subtotal_of === null ? Row::TOTAL : Row::SUBTOTAL) : Row::DETAIL,
                $item['accounts'] === [] ? [] : ['*' => $this->drillToReport('RPT-04', $filters, ['accounts' => array_map('strval', $item['accounts'])])],
                $line->subtotal_of === null ? 0 : 1,
            );
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('code', __('reports.columns.line_code')),
                Column::text('line', __('reports.columns.line')),
                Column::amount('value', __('reports.columns.amount')),
            ],
            rows: $rows,
            controls: $controls,
            filters: $this->describe($filters, asAt: $this->statement() === 'SFP'),
        );
    }
}
