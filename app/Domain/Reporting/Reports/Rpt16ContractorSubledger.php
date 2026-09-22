<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\Counterparty;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-16 Contractor and supplier subledger (Document B §4.5):
 *
 *   outstanding payable  = certified + invoiced − paid − retention held − advance recovered
 *   remaining commitment = contract value + amendments − certified
 *
 * A contractor with no contract value (the alliance, whose contract is still
 * pending) shows its advances and nothing it has not got.
 */
final class Rpt16ContractorSubledger extends BaseReport
{
    public function __construct(LedgerQuery $ledger, private readonly SubledgerQuery $subledger, private readonly AccountSets $sets)
    {
        parent::__construct($ledger);
    }

    public function code(): string
    {
        return 'RPT-16';
    }

    public function filters(): array
    {
        return ['as_at', 'counterparties', 'contracts', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $control = $this->sets->contractorControl();

        $parties = Counterparty::query()
            ->whereIn('id', $this->ledger->lines($filters, range: false)->whereIn('jl.account_id', $control)->whereNotNull('jl.counterparty_id')->distinct()->pluck('jl.counterparty_id'))
            ->when($filters->ids('counterparties') !== [], fn ($q) => $q->whereIn('id', $filters->ids('counterparties')))
            ->orderBy('code')
            ->get();

        $rows = [];
        $totals = array_fill_keys(['contract', 'certified', 'invoiced', 'advances', 'recovered', 'retention', 'paid', 'payable', 'commitment'], 0);

        foreach ($parties as $party) {
            $f = $this->subledger->figures($party->id, null, $filters->asAt());
            $contracts = Contract::query()->with('amendments')->where('counterparty_id', $party->id)->get();
            $contractValue = (int) $contracts->sum(fn (Contract $c): int => (int) $c->contract_value + (int) $c->amendments->sum('value_change'));

            $cells = [
                'contract' => $contractValue,
                'certified' => $f['certified'],
                'invoiced' => $f['invoiced'],
                'advances' => $f['advances'] - $f['recovered'],
                'recovered' => $f['recovered'],
                'retention' => $f['retention'],
                'paid' => $f['paid'],
                'payable' => $f['certified'] + $f['invoiced'] - $f['paid'] - $f['retention'] - $f['recovered'],
                'commitment' => $contractValue === 0 ? 0 : $contractValue - $f['certified'],
            ];

            foreach ($cells as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = new Row(['party' => $party->label(), ...$cells], Row::DETAIL, ['*' => $this->drillToReport('RPT-05', $filters, ['accounts' => array_map('strval', $control), 'counterparties' => [(string) $party->id]])]);
        }

        $rows[] = new Row(['party' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('party', __('reports.columns.counterparty')),
                Column::amount('contract', __('reports.columns.contract_value')),
                Column::amount('certified', __('reports.columns.certified')),
                Column::amount('invoiced', __('reports.columns.invoiced')),
                Column::amount('advances', __('reports.columns.advances_net')),
                Column::amount('recovered', __('reports.columns.recovered')),
                Column::amount('retention', __('reports.columns.retention')),
                Column::amount('paid', __('reports.columns.paid')),
                Column::amount('payable', __('reports.columns.payable')),
                Column::amount('commitment', __('reports.columns.commitment')),
            ],
            rows: $rows,
            filters: $this->describe($filters, asAt: true),
        );
    }
}
