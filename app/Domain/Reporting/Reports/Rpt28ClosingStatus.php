<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Closing\Enums\ChecklistStatus;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/** RPT-28 Monthly closing status: the 25 tasks per period with their owner, status and completion. */
final class Rpt28ClosingStatus extends BaseReport
{
    public function code(): string
    {
        return 'RPT-28';
    }

    public function filters(): array
    {
        return ['period', 'fiscal_year'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $periods = AccountingPeriod::query()
            ->when($filters->has('period'), fn ($q) => $q->whereKey((int) $filters->get('period')))
            ->when(! $filters->has('period'), fn ($q) => $q->whereBetween('starts_on', [$filters->from()->toDateString(), $filters->to()->toDateString()]))
            ->orderBy('starts_on')->get();

        $rows = [];

        foreach ($periods as $period) {
            $tasks = ChecklistTask::query()->with(['responsible', 'completer'])->where('period_id', $period->id)->orderBy('task_no')->get();
            $done = $tasks->where('status', '!=', ChecklistStatus::Pending)->count();

            $rows[] = new Row([
                'task' => $period->period_code.' — '.$period->status->getLabel(),
                'status' => $tasks->isEmpty() ? '—' : (int) round($done * 100 / $tasks->count()).'%',
            ], Row::GROUP);

            foreach ($tasks as $task) {
                $rows[] = new Row([
                    'task' => $task->task_no.'. '.$task->displayName(),
                    'responsible' => $task->responsible?->name,
                    'due' => $task->due_date?->toDateString(),
                    'status' => $task->status->getLabel(),
                    'completed_by' => $task->completer?->name,
                    'comments' => $task->getAttribute('comments'),
                ], Row::DETAIL, [], 1);
            }
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('task', __('reports.columns.task')),
                Column::text('responsible', __('reports.columns.responsible')),
                Column::date('due', __('reports.columns.due')),
                Column::text('status', __('reports.columns.status')),
                Column::text('completed_by', __('reports.columns.completed_by')),
                Column::text('comments', __('reports.columns.comments')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
