<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Advances\AdvancePosition;
use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-14 Advance aging: outstanding advances bucketed by days since issue --
 * 0-30, 31-60, 61-90, 91-180, over 180 -- with the overdue amount beside them.
 */
final class Rpt14AdvanceAging extends Rpt13AdvancesOutstanding
{
    private const BUCKETS = ['d0_30' => 30, 'd31_60' => 60, 'd61_90' => 90, 'd91_180' => 180];

    public function code(): string
    {
        return 'RPT-14';
    }

    public function filters(): array
    {
        return ['as_at', 'advance_holders', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = [];
        $totals = array_fill_keys([...array_keys(self::BUCKETS), 'd180_plus', 'overdue', 'outstanding'], 0);

        foreach ($this->positions($filters)->filter(fn (AdvancePosition $p): bool => $p->outstanding !== 0) as $position) {
            $cells = array_fill_keys(array_keys($totals), 0);
            $cells[$this->bucket($position->ageInDays())] = $position->outstanding;
            $cells['overdue'] = $position->status() === AdvanceStatus::Overdue ? $position->outstanding : 0;
            $cells['outstanding'] = $position->outstanding;

            foreach ($cells as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = new Row([
                'advance' => $position->advance->advance_ref,
                'holder' => $position->advance->holder->displayName(),
                'age' => (string) $position->ageInDays(),
                ...$cells,
            ], Row::DETAIL, $position->advance->issue_journal_id === null ? [] : ['*' => ['journal' => $position->advance->issue_journal_id]]);
        }

        $rows[] = new Row(['advance' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('advance', __('reports.columns.advance')),
                Column::text('holder', __('reports.columns.holder')),
                Column::text('age', __('reports.columns.age_days')),
                Column::amount('d0_30', '0–30'),
                Column::amount('d31_60', '31–60'),
                Column::amount('d61_90', '61–90'),
                Column::amount('d91_180', '91–180'),
                Column::amount('d180_plus', '180+'),
                Column::amount('overdue', __('reports.columns.overdue')),
                Column::amount('outstanding', __('reports.columns.outstanding')),
            ],
            rows: $rows,
            filters: $this->describe($filters, asAt: true),
        );
    }

    private function bucket(int $days): string
    {
        foreach (self::BUCKETS as $key => $limit) {
            if ($days <= $limit) {
                return $key;
            }
        }

        return 'd180_plus';
    }
}
