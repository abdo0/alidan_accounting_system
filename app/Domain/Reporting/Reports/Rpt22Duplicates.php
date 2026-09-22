<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/** RPT-22 Duplicate register: every flagged entry, its match, the value at risk and the disposition. */
final class Rpt22Duplicates extends BaseReport
{
    public function code(): string
    {
        return 'RPT-22';
    }

    public function filters(): array
    {
        return ['flag_types', 'counterparties', 'from', 'to'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $flags = DuplicateFlag::query()
            ->with(['journal', 'matchedJournal'])
            ->when($filters->list('flag_types') !== [], fn ($q) => $q->whereIn('flag_type', $filters->list('flag_types')))
            ->orderBy('id')
            ->get();

        $rows = $flags->map(fn (DuplicateFlag $flag): Row => new Row([
            'jv_no' => $flag->journal->jv_no ?? '#'.$flag->journal_header_id,
            'matched' => $flag->matchedJournal?->jv_no,
            'type' => $flag->flag_type->getLabel(),
            'reason' => $flag->match_reason,
            'source_review' => $flag->source_review,
            'value_at_risk' => $flag->value_at_risk,
            'disposition' => $flag->disposition?->getLabel() ?? __('pages.duplicate.undecided'),
        ], Row::DETAIL, ['*' => ['journal' => $flag->journal_header_id]]))->all();

        $rows[] = new Row(['reason' => __('reports.total'), 'value_at_risk' => (int) $flags->sum('value_at_risk')], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('jv_no', __('reports.columns.jv_no')),
                Column::text('matched', __('reports.columns.matched')),
                Column::text('type', __('reports.columns.type')),
                Column::text('reason', __('reports.columns.reason')),
                Column::text('source_review', __('reports.columns.source_review')),
                Column::amount('value_at_risk', __('reports.columns.value_at_risk')),
                Column::text('disposition', __('reports.columns.disposition')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
