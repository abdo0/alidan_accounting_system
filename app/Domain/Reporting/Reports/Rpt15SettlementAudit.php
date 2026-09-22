<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Advances\AdvanceSettlement;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-15 Advance settlement audit: every settlement with its classification,
 * evidence status and reviewer -- the expense-audit working paper as a live
 * report. Pending-evidence claims appear with their claimed amount and no posting.
 */
final class Rpt15SettlementAudit extends BaseReport
{
    public function code(): string
    {
        return 'RPT-15';
    }

    public function filters(): array
    {
        return ['advance_holders', 'from', 'to'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $settlements = AdvanceSettlement::query()
            ->with(['advance.holder', 'line.header', 'reviewer'])
            ->when($filters->ids('advance_holders') !== [], fn ($q) => $q->whereHas('advance', fn ($q) => $q->whereIn('holder_id', $filters->ids('advance_holders'))))
            ->orderBy('advance_id')->orderBy('id')
            ->get();

        $rows = $settlements->map(fn (AdvanceSettlement $s): Row => new Row([
            'advance' => $s->advance->advance_ref,
            'holder' => $s->advance->holder->displayName(),
            'jv_no' => $s->line?->header->jv_no,
            'type' => $s->settlement_type->getLabel(),
            'amount' => $s->line === null ? null : $s->line->credit - $s->line->debit,
            'claimed' => $s->claimed_amount,
            'classification' => $s->classification?->getLabel(),
            'evidence' => $s->evidence_status?->getLabel(),
            'reviewer' => $s->reviewer?->name,
        ], Row::DETAIL, $s->line === null ? [] : ['*' => ['journal' => $s->line->journal_header_id]]))->all();

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('advance', __('reports.columns.advance')),
                Column::text('holder', __('reports.columns.holder')),
                Column::text('jv_no', __('reports.columns.jv_no')),
                Column::text('type', __('reports.columns.type')),
                Column::amount('amount', __('reports.columns.amount')),
                Column::amount('claimed', __('reports.columns.claimed')),
                Column::text('classification', __('reports.columns.classification')),
                Column::text('evidence', __('reports.columns.evidence')),
                Column::text('reviewer', __('reports.columns.reviewer')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
