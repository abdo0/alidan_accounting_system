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
 * RPT-20 Funding chain control (Document B §2.6):
 *
 *   Level 1 = Σ credits to the shareholder loan accounts
 *   Level 2 = Σ debits to the advance accounts
 *   Level 3 = Σ credits out of the advance accounts to final accounts
 *   Difference = Level 2 − Level 3
 *
 * The difference is reported at its true value and never forced to zero: on the
 * migrated ledger, 112035 carries a net credit of IQD 99,796,000 and appears here
 * as exactly that.
 */
final class Rpt20FundingChain extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-20';
    }

    public function filters(): array
    {
        return ['from', 'to', 'advance_holders', 'funding_batches', 'source_presences', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $loans = $this->sets->onLine('SFP-L-140');
        $advances = $this->sets->advances();

        $level1 = (int) $this->ledger->lines($filters->with(['advance_holders' => []]))->whereIn('jl.account_id', $loans)->sum('jl.credit');

        $perAccount = $this->ledger->lines($filters)
            ->whereIn('jl.account_id', $advances)
            ->groupBy('jl.account_id')
            ->selectRaw('jl.account_id, sum(jl.debit) AS level2, sum(jl.credit) AS level3')
            ->get()
            ->keyBy('account_id');

        $rows = [new Row(['level' => __('reports.chain.level1'), 'amount' => $level1], Row::SUBTOTAL, ['*' => $this->drillToReport('RPT-04', $filters, ['accounts' => array_map('strval', $loans)])])];
        $level2 = 0;
        $level3 = 0;

        foreach (Account::query()->whereIn('id', $perAccount->keys())->orderBy('code')->get() as $account) {
            $row = $perAccount[$account->id];
            $level2 += (int) $row->level2;
            $level3 += (int) $row->level3;

            $rows[] = new Row([
                'level' => $account->label(),
                'level2' => (int) $row->level2,
                'level3' => (int) $row->level3,
                'difference' => (int) $row->level2 - (int) $row->level3,
            ], Row::DETAIL, ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => [(string) $account->id]])], 1);
        }

        $rows[] = new Row([
            'level' => __('reports.chain.levels_2_3'),
            'level2' => $level2,
            'level3' => $level3,
            'difference' => $level2 - $level3,
        ], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('level', __('reports.columns.level')),
                Column::amount('amount', __('reports.chain.level1')),
                Column::amount('level2', __('reports.chain.level2')),
                Column::amount('level3', __('reports.chain.level3')),
                Column::amount('difference', __('reports.columns.difference')),
            ],
            rows: $rows,
            controls: [new Control(__('reports.chain.open_difference'), $level2, $level3)],
            filters: $this->describe($filters),
            notes: [__('reports.chain.note')],
        );
    }
}
