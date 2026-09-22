<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\Account;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-17 Contractor reconciliation to the GL: for each contractor and supplier
 * control account, the subledger total (lines carrying a counterparty) against the
 * general-ledger balance. The difference must be zero; if it is not, it is shown.
 */
final class Rpt17ContractorReconciliation extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-17';
    }

    public function filters(): array
    {
        return ['as_at', 'counterparties'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = [];
        $controls = [];

        foreach (Account::query()->whereIn('id', $this->sets->contractorControl())->orderBy('code')->get() as $account) {
            $base = $this->ledger->lines($filters, range: false)
                ->where('jh.posting_date', '<=', $filters->asAt()->toDateString())
                ->where('jl.account_id', $account->id);

            $gl = (int) (clone $base)->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS n')->value('n');
            $sub = (int) (clone $base)->whereNotNull('jl.counterparty_id')->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS n')->value('n');

            $rows[] = new Row([
                'account' => $account->label(),
                'subledger' => $sub,
                'gl' => $gl,
                'difference' => $sub - $gl,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => [(string) $account->id]])]);

            $controls[] = new Control(__('reports.controls.subledger_agrees', ['account' => $account->code]), $sub, $gl);
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('account', __('reports.columns.account')),
                Column::amount('subledger', __('reports.columns.subledger')),
                Column::amount('gl', __('reports.columns.general_ledger')),
                Column::amount('difference', __('reports.columns.difference')),
            ],
            rows: $rows,
            controls: $controls,
            filters: $this->describe($filters, asAt: true),
        );
    }
}
