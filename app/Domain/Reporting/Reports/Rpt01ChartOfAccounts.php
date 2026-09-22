<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Account;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportMapping;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/** RPT-01 Chart of accounts, with each account's statement mapping. */
final class Rpt01ChartOfAccounts extends BaseReport
{
    public function code(): string
    {
        return 'RPT-01';
    }

    public function filters(): array
    {
        return ['account_groups'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $mapping = ReportMapping::query()->whereNull('effective_to')->pluck('fs_line_code', 'account_id');

        $rows = Account::query()->orderBy('code')->with('parent')
            ->when($filters->ids('account_groups') !== [], fn ($q) => $q->where(fn ($q) => $q->whereIn('id', $filters->ids('account_groups'))
                ->orWhereIn('parent_id', $filters->ids('account_groups'))
                ->orWhereHas('parent', fn ($q) => $q->whereIn('parent_id', $filters->ids('account_groups')))))
            ->get()
            ->map(fn (Account $a): Row => new Row([
                'code' => $a->code,
                'name' => $a->displayName(),
                'type' => $a->account_type->getLabel(),
                'parent' => $a->parent?->code,
                'level' => (string) $a->account_level,
                'posting' => $a->is_group ? __('reports.group') : ($a->is_posting ? __('reports.yes') : __('reports.no')),
                'active' => $a->is_active ? __('reports.yes') : __('reports.no'),
                'normal' => $a->normal_balance->getLabel(),
                'fs_line' => $mapping[$a->id] ?? null,
            ], $a->is_group ? Row::GROUP : Row::DETAIL, [], $a->account_level - 1))
            ->all();

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('code', __('reports.columns.code')),
                Column::text('name', __('reports.columns.account')),
                Column::text('type', __('reports.columns.type')),
                Column::text('parent', __('reports.columns.parent')),
                Column::text('level', __('reports.columns.level')),
                Column::text('posting', __('reports.columns.posting')),
                Column::text('active', __('reports.columns.active')),
                Column::text('normal', __('reports.columns.normal_balance')),
                Column::text('fs_line', __('reports.columns.line_code')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
