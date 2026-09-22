<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Migration\MigrationRun;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/** RPT-29 Migration reconciliation: the thirty controls of the latest committed run, source against system. */
final class Rpt29MigrationReconciliation extends BaseReport
{
    public function code(): string
    {
        return 'RPT-29';
    }

    public function filters(): array
    {
        return [];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $run = MigrationRun::query()->where('mode', 'commit')->where('status', 'completed')->latest('id')->with('controls')->first();

        $rows = $run === null ? [] : $run->controls->map(fn ($c): Row => new Row([
            'control' => $c->control_code,
            'measure' => $c->control_name,
            'source' => $c->source_value,
            'system' => $c->system_value,
            'status' => $c->status === 'pass' ? __('reports.passes') : __('reports.fails'),
        ]))->all();

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('control', __('reports.columns.control')),
                Column::text('measure', __('reports.columns.measure')),
                Column::text('source', __('reports.columns.source_value')),
                Column::text('system', __('reports.columns.system_value')),
                Column::text('status', __('reports.columns.status')),
            ],
            rows: $rows,
            filters: $run === null ? [] : [__('reports.columns.run') => $run->run_ref.' · '.$run->source_file],
            notes: $run === null ? [__('migration.no_run')] : [],
        );
    }
}
