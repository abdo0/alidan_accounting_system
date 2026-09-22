<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Carbon\CarbonImmutable;

/** RPT-23 Unposted and review-required register: every entry not yet posted, with its age and owner. */
final class Rpt23Unposted extends BaseReport
{
    public function code(): string
    {
        return 'RPT-23';
    }

    public function filters(): array
    {
        return ['statuses'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $statuses = $filters->list('statuses') ?: [JournalStatus::Draft->value, JournalStatus::Submitted->value, JournalStatus::Reviewed->value, JournalStatus::Approved->value];

        $journals = JournalHeader::query()
            ->with(['lines', 'creator', 'transactionType'])
            ->whereIn('status', $statuses)
            ->when($user->permissionScope('journal.view')?->value === 'own', fn ($q) => $q->where('created_by', $user->id))
            ->orderBy('created_at')
            ->get();

        $rows = $journals->map(fn (JournalHeader $j): Row => new Row([
            'id' => '#'.$j->id,
            'status' => $j->status->getLabel(),
            'type' => $j->transactionType->code,
            'description' => $j->description_ar,
            'amount' => $j->totalDebit(),
            'owner' => $j->creator?->name,
            'age' => (string) (int) CarbonImmutable::parse($j->created_at)->diffInDays(now()),
        ], Row::DETAIL, ['*' => ['journal' => $j->id]]))->all();

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('id', __('reports.columns.jv_no')),
                Column::text('status', __('reports.columns.status')),
                Column::text('type', __('reports.columns.type')),
                Column::text('description', __('reports.columns.description')),
                Column::amount('amount', __('reports.columns.amount')),
                Column::text('owner', __('reports.columns.owner')),
                Column::text('age', __('reports.columns.age_days')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
