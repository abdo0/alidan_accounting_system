<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-08b Management Funding & Cash Movement Summary: funding received, funding
 * utilised by use, recoveries and closing cash -- the File 1 funding-summary view.
 * It is explicitly NOT the statutory cash flow statement (RPT-08a), and says so on
 * screen and in every export (Document B §4.6).
 */
final class Rpt08bFundingSummary extends Rpt12FundingUtilisation
{
    public function __construct(LedgerQuery $ledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-08b';
    }

    public function filters(): array
    {
        return ['from', 'to', 'projects', 'shareholders', 'funding_batches'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $cash = $this->sets->cash();
        $claims = array_merge([], ...array_values($this->sets->shareholderClaims()));
        $thirdParty = $this->sets->ofRule('PR-06', 'credit');

        $openingCash = array_sum(array_intersect_key($this->ledger->balancesAsAt($filters, $filters->from()->subDay()), array_flip($cash)));
        $closingCash = array_sum(array_intersect_key($this->ledger->balancesAsAt($filters, $filters->to()), array_flip($cash)));

        $received = (int) $this->ledger->lines($filters)->whereIn('jl.account_id', $claims)->sum('jl.credit');
        $receivedThird = (int) $this->ledger->lines($filters)->whereIn('jl.account_id', $thirdParty)->sum('jl.credit');
        $recoveries = (int) $this->ledger->lines($filters)
            ->join('transaction_types as tt', 'tt.id', '=', 'jh.transaction_type_id')
            ->whereIn('tt.code', ['TT-13'])
            ->whereIn('jl.account_id', $cash)
            ->sum('jl.debit');

        $rows = [
            new Row(['item' => __('reports.funding.opening_cash'), 'amount' => $openingCash], Row::SUBTOTAL),
            new Row(['item' => __('reports.funding.received')], Row::GROUP),
            new Row(['item' => __('reports.funding.shareholders'), 'amount' => $received], Row::DETAIL, ['*' => $this->drillToReport('RPT-11', $filters)], 1),
            new Row(['item' => __('reports.funding.third_party'), 'amount' => $receivedThird], Row::DETAIL, [], 1),
            new Row(['item' => __('reports.funding.utilised')], Row::GROUP),
        ];

        foreach ($this->uses($filters) as $use) {
            $rows[] = new Row(['item' => $use['label'], 'amount' => $use['amount']], Row::DETAIL, ['*' => $this->drillToReport('RPT-04', $filters, ['accounts' => $use['accounts']])], 1);
        }

        $rows[] = new Row(['item' => __('reports.funding.recoveries'), 'amount' => $recoveries], Row::DETAIL);
        $rows[] = new Row(['item' => __('reports.funding.closing_cash'), 'amount' => $closingCash], Row::TOTAL, ['*' => $this->drillToReport('RPT-10', $filters)]);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('item', __('reports.columns.item')),
                Column::amount('amount', __('reports.columns.amount')),
            ],
            rows: $rows,
            filters: $this->describe($filters),
            notes: [__('reports.funding.not_statutory')],
        );
    }
}
