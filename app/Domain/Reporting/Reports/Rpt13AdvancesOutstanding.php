<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvancePosition;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * RPT-13 Advances outstanding: per advance and holder -- issued, settled, refunded,
 * reclassified, sub-advanced, outstanding, status -- and, per advance account, the
 * register agreed to the general ledger. A balance on an advance account that no
 * advance explains is shown as such, never hidden (Document B §2.6).
 */
class Rpt13AdvancesOutstanding extends BaseReport
{
    public function code(): string
    {
        return 'RPT-13';
    }

    public function filters(): array
    {
        return ['as_at', 'advance_holders', 'resp_centers', 'projects'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $positions = $this->positions($filters);
        $rows = [];
        $totals = array_fill_keys(['issued', 'settled', 'refunded', 'reclassified', 'sub_advanced', 'other', 'outstanding'], 0);

        foreach ($positions->groupBy(fn (AdvancePosition $p): int => $p->advance->account_id) as $group) {
            $account = $group->first()->advance->account;
            $rows[] = new Row(['advance' => $account->label()], Row::GROUP);

            foreach ($group as $position) {
                $cells = $this->cells($position);

                foreach ($totals as $key => $value) {
                    $totals[$key] += $cells[$key];
                }

                $rows[] = new Row($cells, Row::DETAIL, $position->advance->issue_journal_id === null ? [] : ['*' => ['journal' => $position->advance->issue_journal_id]], 1);
            }
        }

        $rows[] = new Row(['advance' => __('reports.total'), ...$totals], Row::TOTAL);

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('advance', __('reports.columns.advance')),
                Column::text('holder', __('reports.columns.holder')),
                Column::date('issue_date', __('reports.columns.issue_date')),
                Column::date('deadline', __('reports.columns.deadline')),
                Column::amount('issued', __('reports.columns.issued')),
                Column::amount('settled', __('reports.columns.settled')),
                Column::amount('refunded', __('reports.columns.refunded')),
                Column::amount('reclassified', __('reports.columns.reclassified')),
                Column::amount('sub_advanced', __('reports.columns.sub_advanced')),
                Column::amount('other', __('reports.columns.other')),
                Column::amount('outstanding', __('reports.columns.outstanding')),
                Column::text('status', __('reports.columns.status')),
            ],
            rows: $rows,
            controls: $this->ledgerAgreement($filters, $positions),
            filters: $this->describe($filters, asAt: true),
        );
    }

    /** @return Collection<int, AdvancePosition> */
    protected function positions(FilterSet $filters): Collection
    {
        return Advance::query()
            ->with(['holder', 'account'])
            ->when($filters->ids('advance_holders') !== [], fn ($q) => $q->whereIn('holder_id', $filters->ids('advance_holders')))
            ->when($filters->ids('resp_centers') !== [], fn ($q) => $q->whereIn('resp_center_id', $filters->ids('resp_centers')))
            ->when($filters->ids('projects') !== [], fn ($q) => $q->whereIn('project_id', $filters->ids('projects')))
            ->where('issue_date', '<=', $filters->asAt()->toDateString())
            ->orderBy('account_id')->orderBy('issue_date')
            ->get()
            ->map(fn (Advance $a): AdvancePosition => AdvancePosition::of($a, $filters->asAt()));
    }

    /** @return array<string, int|string|null> */
    protected function cells(AdvancePosition $p): array
    {
        return [
            'advance' => $p->advance->advance_ref,
            'holder' => $p->advance->holder->displayName(),
            'issue_date' => $p->advance->issue_date->toDateString(),
            'deadline' => $p->advance->settlement_deadline?->toDateString(),
            'issued' => $p->issued,
            'settled' => $p->settled(SettlementType::Capex, SettlementType::Opex, SettlementType::FixedAsset, SettlementType::Acquisition),
            'refunded' => $p->settled(SettlementType::Refund, SettlementType::Recovery),
            'reclassified' => $p->settled(SettlementType::Reclassification),
            'sub_advanced' => $p->settled(SettlementType::SubAdvance),
            'other' => $p->settled(SettlementType::Shortfall, SettlementType::Other),
            'outstanding' => $p->outstanding,
            'status' => $p->status()->getLabel(),
        ];
    }

    /**
     * Per advance account: the register's outstanding total against the ledger
     * balance. A difference is reported at its true value.
     *
     * @param  Collection<int, AdvancePosition>  $positions
     * @return list<Control>
     */
    private function ledgerAgreement(FilterSet $filters, Collection $positions): array
    {
        $balances = $this->ledger->balancesAsAt(FilterSet::fromArray(['as_at' => $filters->asAt()->toDateString()]));
        $controls = [];

        foreach ($positions->groupBy(fn (AdvancePosition $p): int => $p->advance->account_id) as $accountId => $group) {
            $controls[] = new Control(
                __('reports.controls.register_agrees', ['account' => $group->first()->advance->account->code]),
                (int) $group->sum(fn (AdvancePosition $p): int => $p->outstanding),
                $balances[$accountId] ?? 0,
            );
        }

        return $controls;
    }
}
