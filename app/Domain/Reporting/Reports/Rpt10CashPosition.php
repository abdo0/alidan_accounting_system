<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Cash\BookBalance;
use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Reconciliation;
use App\Domain\MasterData\BankAccount;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-10 Cash position: per cash account, safe and bank -- opening, receipts,
 * payments and closing book balance (all from the ledger), then the latest
 * reconciliation: actual balance, variance, status, date, responsible accountant.
 */
final class Rpt10CashPosition extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly BookBalance $books)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-10';
    }

    public function filters(): array
    {
        return ['from', 'to', 'cash_accounts', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = [];
        $totals = array_fill_keys(['opening', 'receipts', 'payments', 'closing'], 0);

        $accounts = BankAccount::query()->with(['account', 'custodian', 'responsibleUser'])
            ->when($filters->ids('cash_accounts') !== [], fn ($q) => $q->whereIn('id', $filters->ids('cash_accounts')))
            ->when($filters->ids('projects') !== [], fn ($q) => $q->whereIn('project_id', $filters->ids('projects')))
            ->orderBy('code')->get();

        foreach ($accounts as $account) {
            $opening = $this->books->at($account, $filters->from()->subDay());
            $movement = $this->ledger->lines($filters->with(['cash_accounts' => [], 'projects' => []]))
                ->where('jl.account_id', $account->account_id)
                ->selectRaw('coalesce(sum(jl.debit), 0) AS receipts, coalesce(sum(jl.credit), 0) AS payments')
                ->first();
            $closing = $opening + (int) $movement->receipts - (int) $movement->payments;

            $latest = Reconciliation::query()->with('responsible')
                ->where('bank_account_id', $account->id)
                ->where('as_at_date', '<=', $filters->to()->toDateString())
                ->orderByDesc('as_at_date')->orderByDesc('id')
                ->first();

            $variance = $latest === null ? null : $latest->actual_balance - $this->books->at($account, $latest->as_at_date);

            $cells = ['opening' => $opening, 'receipts' => (int) $movement->receipts, 'payments' => (int) $movement->payments, 'closing' => $closing];
            foreach ($cells as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = new Row([
                'account' => $account->code.' — '.$account->displayName(),
                ...$cells,
                'actual' => $latest?->actual_balance,
                'variance' => $variance,
                'status' => $latest === null
                    ? __('reports.cash.never_reconciled')
                    : ($latest->status === ReconciliationStatus::Approved && $variance === 0 ? __('reports.cash.reconciled') : __('reports.cash.unreconciled')),
                'reconciled_on' => $latest?->as_at_date->toDateString(),
                'responsible' => $latest?->responsible->name ?? $account->responsibleUser?->name,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => [(string) $account->account_id]])]);
        }

        $rows[] = new Row(['account' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('account', __('reports.columns.account')),
                Column::amount('opening', __('reports.columns.opening')),
                Column::amount('receipts', __('reports.columns.receipts')),
                Column::amount('payments', __('reports.columns.payments')),
                Column::amount('closing', __('reports.columns.book_balance')),
                Column::amount('actual', __('reports.columns.actual_balance')),
                Column::amount('variance', __('reports.columns.variance')),
                Column::text('status', __('reports.columns.status')),
                Column::date('reconciled_on', __('reports.columns.reconciled_on')),
                Column::text('responsible', __('reports.columns.responsible')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
        );
    }
}
