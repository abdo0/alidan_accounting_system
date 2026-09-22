<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Organisation\Company;
use App\Domain\Reporting\Filters\FilterApplier;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RPT-03 Journal report: headers and lines with their dimensions and control
 * fields, every filter of the engine available. Unlike the ledger reports it can
 * include entries not yet posted, when a status filter asks for them; an
 * Own-scope user then sees only their own unposted work.
 */
final class Rpt03Journal extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly FilterApplier $applier)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-03';
    }

    public function filters(): array
    {
        return array_keys(array_diff_key(FilterSet::CODES, ['comparative' => true, 'flag_types' => true]));
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $statuses = $filters->list('statuses') ?: JournalStatus::ledgerValues();

        $query = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('projects as p', 'p.id', '=', 'jl.project_id')
            ->join('transaction_types as tt', 'tt.id', '=', 'jh.transaction_type_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'jl.cost_center_id')
            ->leftJoin('counterparties as cp', 'cp.id', '=', 'jl.counterparty_id')
            ->where('jh.company_id', Company::current()->id)
            ->whereIn('jh.status', $statuses);

        $this->applier->apply($query, $filters->with(['statuses' => []]));

        if ($user->permissionScope('journal.view')?->value === 'own') {
            $query->where(fn ($q) => $q->where('jh.created_by', $user->id)->orWhereIn('jh.status', JournalStatus::ledgerValues()));
        }

        $lines = $query
            ->orderBy('jh.posting_date')->orderBy('jh.id')->orderBy('jl.line_no')
            ->select([
                'jh.id as journal_id', 'jh.jv_no', 'jh.status', 'jh.posting_date', 'jh.txn_date', 'jh.date_status', 'jh.doc_status',
                'tt.code as type', 'jh.description_ar', 'jh.source_reference', 'jl.line_no', 'a.code as account',
                'p.code as project', 'cc.code as cost_center', 'cp.code as counterparty', 'jl.debit', 'jl.credit',
            ])
            ->get();

        $rows = $lines->map(fn ($l): Row => new Row([
            'jv_no' => $l->jv_no ?? '#'.$l->journal_id,
            'status' => __('enums.journal_status.'.$l->status),
            'posting_date' => $l->posting_date,
            'txn_date' => $l->txn_date,
            'type' => $l->type,
            'description' => $l->description_ar,
            'line' => (int) $l->line_no,
            'account' => $l->account,
            'project' => $l->project,
            'cost_center' => $l->cost_center,
            'counterparty' => $l->counterparty,
            'debit' => (int) $l->debit,
            'credit' => (int) $l->credit,
            'doc_status' => __('enums.doc_status.'.$l->doc_status),
            'source_reference' => $l->source_reference,
        ], Row::DETAIL, ['*' => ['journal' => (int) $l->journal_id]]))->all();

        $rows[] = new Row(['description' => __('reports.total'), 'debit' => (int) $lines->sum('debit'), 'credit' => (int) $lines->sum('credit')], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('jv_no', __('reports.columns.jv_no')),
                Column::text('status', __('reports.columns.status')),
                Column::date('posting_date', __('reports.columns.posting_date')),
                Column::date('txn_date', __('reports.columns.txn_date')),
                Column::text('type', __('reports.columns.type')),
                Column::text('description', __('reports.columns.description')),
                Column::text('line', __('reports.columns.line')),
                Column::text('account', __('reports.columns.code')),
                Column::text('project', __('reports.columns.project')),
                Column::text('cost_center', __('reports.columns.cost_center')),
                Column::text('counterparty', __('reports.columns.counterparty')),
                Column::amount('debit', __('reports.columns.debit')),
                Column::amount('credit', __('reports.columns.credit')),
                Column::text('doc_status', __('reports.columns.doc_status')),
                Column::text('source_reference', __('reports.columns.source_reference')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
