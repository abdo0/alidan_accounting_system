<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Filters;

use Illuminate\Database\Query\Builder;

/**
 * Applies a filter set to a query over journal_lines (jl) joined to
 * journal_headers (jh). Every filter binds to a fixed column from this whitelist
 * and every value is a bound parameter: no SQL is ever built from user input,
 * including here (Document B §8).
 */
final class FilterApplier
{
    /** Filter key => bound column (Document C tab 17 "Bound field"). */
    public const COLUMNS = [
        'projects' => 'jl.project_id',
        'cost_centers' => 'jl.cost_center_id',
        'resp_centers' => 'jl.resp_center_id',
        'accounts' => 'jl.account_id',
        'funding_sources' => 'jl.funding_source_id',
        'funding_batches' => 'jl.funding_batch_id',
        'funding_categories' => 'jl.funding_category_id',
        'chain_steps' => 'jl.chain_step_id',
        'shareholders' => 'jl.counterparty_id',
        'counterparties' => 'jl.counterparty_id',
        'advance_holders' => 'jl.advance_holder_id',
        'contracts' => 'jl.contract_id',
        'work_packages' => 'jl.work_package_id',
        'asset_classes' => 'jl.asset_class',
        'handover' => 'jl.handover_req',
        'statuses' => 'jh.status',
        'doc_statuses' => 'jh.doc_status',
        'recon_statuses' => 'jl.recon_status',
        'date_statuses' => 'jh.date_status',
        'source_presences' => 'jh.source_presence',
        'cash_accounts' => 'jl.cash_account_id',
    ];

    /**
     * @param  bool  $range  apply the posting-date range (false for an as-at balance, which
     *                       applies only the upper bound itself)
     */
    public function apply(Builder $query, FilterSet $filters, bool $range = true): Builder
    {
        if ($range) {
            $query->whereBetween('jh.posting_date', [$filters->from()->toDateString(), $filters->to()->toDateString()]);
        }

        foreach (self::COLUMNS as $key => $column) {
            $values = $filters->list($key);

            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        if ($filters->has('capex_opex')) {
            $query->where('jl.capex_opex', $filters->get('capex_opex'));
        }

        if ($filters->list('account_groups') !== []) {
            $query->whereIn('jl.account_id', fn (Builder $q) => $q->select('id')->from('accounts')->whereIn('parent_id', $filters->ids('account_groups'))
                ->orWhereIn('parent_id', fn (Builder $inner) => $inner->select('id')->from('accounts')->whereIn('parent_id', $filters->ids('account_groups'))));
        }

        if ($filters->has('txn_from')) {
            $query->where('jh.txn_date', '>=', $filters->get('txn_from'));
        }

        if ($filters->has('txn_to')) {
            $query->where('jh.txn_date', '<=', $filters->get('txn_to'));
        }

        return $query;
    }
}
