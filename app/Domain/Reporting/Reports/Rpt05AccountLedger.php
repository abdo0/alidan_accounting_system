<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-05 Account ledger: every movement on the chosen accounts with a running
 * balance, each drilling to its journal entry and from there to its documents.
 */
final class Rpt05AccountLedger extends BaseReport
{
    public function code(): string
    {
        return 'RPT-05';
    }

    public function filters(): array
    {
        return ['accounts', 'from', 'to', 'fiscal_year', 'period', 'projects', 'cost_centers', 'counterparties'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $opening = (int) $this->ledger->lines($filters, range: false)
            ->where('jh.posting_date', '<', $filters->from()->toDateString())
            ->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS net')
            ->value('net');

        $lines = $this->ledger->lines($filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->leftJoin('counterparties as cp', 'cp.id', '=', 'jl.counterparty_id')
            ->orderBy('jh.posting_date')->orderBy('jh.id')->orderBy('jl.line_no')
            ->select([
                'jh.id as journal_id', 'jh.jv_no', 'jh.posting_date', 'jh.txn_date', 'jh.description_ar', 'jh.description_en',
                'a.code as account', 'cp.code as counterparty', 'jl.debit', 'jl.credit',
            ])
            ->get();

        $rows = [new Row(['description' => __('reports.opening_balance'), 'balance' => $opening], Row::SUBTOTAL)];
        $balance = $opening;

        foreach ($lines as $line) {
            $balance += (int) $line->debit - (int) $line->credit;

            $rows[] = new Row([
                'posting_date' => $line->posting_date,
                'txn_date' => $line->txn_date,
                'jv_no' => $line->jv_no,
                'account' => $line->account,
                'description' => app()->getLocale() === 'en' && $line->description_en ? $line->description_en : $line->description_ar,
                'counterparty' => $line->counterparty,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $balance,
            ], Row::DETAIL, ['*' => ['journal' => (int) $line->journal_id]]);
        }

        $rows[] = new Row([
            'description' => __('reports.closing_balance'),
            'debit' => (int) $lines->sum('debit'),
            'credit' => (int) $lines->sum('credit'),
            'balance' => $balance,
        ], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::date('posting_date', __('reports.columns.posting_date')),
                Column::date('txn_date', __('reports.columns.txn_date')),
                Column::text('jv_no', __('reports.columns.jv_no')),
                Column::text('account', __('reports.columns.code')),
                Column::text('description', __('reports.columns.description')),
                Column::text('counterparty', __('reports.columns.counterparty')),
                Column::amount('debit', __('reports.columns.debit')),
                Column::amount('credit', __('reports.columns.credit')),
                Column::amount('balance', __('reports.columns.balance')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
