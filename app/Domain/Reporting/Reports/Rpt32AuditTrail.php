<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RPT-32 Audit trail: every create, change and workflow action with its user,
 * time, object and field-level old and new values, read from the append-only log.
 */
class Rpt32AuditTrail extends BaseReport
{
    /** Rows returned per run; a larger trail is narrowed with the date filters. */
    private const LIMIT = 500;

    public function code(): string
    {
        return 'RPT-32';
    }

    public function permission(): string
    {
        return 'audit.view';
    }

    public function filters(): array
    {
        return ['from', 'to'];
    }

    /** @return list<string>|null restrict to these actions; null means all */
    protected function actions(): ?array
    {
        return null;
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->whereBetween('a.action_at', [$filters->from()->startOfDay(), $filters->to()->endOfDay()])
            ->when($this->actions() !== null, fn ($q) => $q->whereIn('a.action', $this->actions()))
            ->orderByDesc('a.audit_id')
            ->limit(self::LIMIT + 1)
            ->get(['a.audit_id', 'a.action_at', 'u.name as user', 'a.object_type', 'a.object_id', 'a.action', 'a.field_name', 'a.old_value', 'a.new_value', 'a.reason'])
            ->map(fn ($r): Row => new Row([
                'when' => (string) $r->action_at,
                'user' => $r->user,
                'object' => $r->object_type.($r->object_id === null ? '' : ' #'.$r->object_id),
                'action' => $r->action,
                'field' => $r->field_name,
                'old' => $this->short($r->old_value),
                'new' => $this->short($r->new_value),
                'reason' => $r->reason,
            ], Row::DETAIL, $r->object_type === 'journal_headers' && $r->object_id !== null ? ['*' => ['journal' => (int) $r->object_id]] : []))
            ->all();

        $notes = [];
        if (count($rows) > self::LIMIT) {
            $rows = array_slice($rows, 0, self::LIMIT);
            $notes[] = __('reports.truncated', ['limit' => self::LIMIT]);
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('when', __('reports.columns.when')),
                Column::text('user', __('reports.columns.user')),
                Column::text('object', __('reports.columns.object')),
                Column::text('action', __('reports.columns.action')),
                Column::text('field', __('reports.columns.field')),
                Column::text('old', __('reports.columns.old_value')),
                Column::text('new', __('reports.columns.new_value')),
                Column::text('reason', __('reports.columns.reason')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
            notes: $notes,
        );
    }

    private function short(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $value = json_decode($json, true);
        $text = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);

        return mb_strimwidth((string) $text, 0, 160, '…');
    }
}
