<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Maintains gl_balances inside the posting transaction.
 *
 * Two hazards drive the shape of this class, both certain rather than theoretical:
 *
 * 1. A compound entry can carry several lines to the same (account, cost centre,
 *    currency) -- a reversal of a split cost does exactly that. Issuing those as one
 *    multi-row INSERT ... ON CONFLICT raises SQLSTATE 21000, "ON CONFLICT DO UPDATE
 *    command cannot affect row a second time". So lines are aggregated in PHP first.
 *
 * 2. Issuing them as N separate statements in line order lets two concurrent entries
 *    touching overlapping accounts take row locks in opposite orders and deadlock.
 *    So the aggregated keys are sorted deterministically before writing.
 *
 * Reversals are new entries with the sides swapped; this never subtracts.
 */
final class BalanceUpdater
{
    public function apply(JournalEntry $entry): void
    {
        $buckets = [];

        foreach ($entry->lines as $line) {
            $key = implode('|', [
                $entry->entity_id,
                $line->fiscal_period_id,
                $line->account_id,
                $line->cost_centre_id ?? 'null',
                $line->currency_code,
            ]);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'entity_id' => $entry->entity_id,
                    'fiscal_period_id' => $line->fiscal_period_id,
                    'account_id' => $line->account_id,
                    'cost_centre_id' => $line->cost_centre_id,
                    'currency_code' => $line->currency_code,
                    'debit' => '0',
                    'credit' => '0',
                    'functional_debit' => '0',
                    'functional_credit' => '0',
                ];
            }

            $buckets[$key]['debit'] = bcadd($buckets[$key]['debit'], (string) $line->debit_amount, 4);
            $buckets[$key]['credit'] = bcadd($buckets[$key]['credit'], (string) $line->credit_amount, 4);
            $buckets[$key]['functional_debit'] = bcadd($buckets[$key]['functional_debit'], (string) $line->functional_debit, 4);
            $buckets[$key]['functional_credit'] = bcadd($buckets[$key]['functional_credit'], (string) $line->functional_credit, 4);
        }

        // Deterministic order across every transaction in the system.
        ksort($buckets);

        foreach ($buckets as $bucket) {
            $this->upsert($bucket);
        }
    }

    /** @param  array<string, mixed>  $bucket */
    private function upsert(array $bucket): void
    {
        // Targets the named constraint rather than inferring from columns: the index
        // is NULLS NOT DISTINCT, and inference is the kind of thing that silently
        // changes behaviour.
        DB::statement(
            'INSERT INTO gl_balances (
                entity_id, fiscal_period_id, account_id, cost_centre_id, currency_code,
                period_debit, period_credit, functional_period_debit, functional_period_credit,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, now())
             ON CONFLICT ON CONSTRAINT gl_balances_key DO UPDATE SET
                period_debit             = gl_balances.period_debit + EXCLUDED.period_debit,
                period_credit            = gl_balances.period_credit + EXCLUDED.period_credit,
                functional_period_debit  = gl_balances.functional_period_debit + EXCLUDED.functional_period_debit,
                functional_period_credit = gl_balances.functional_period_credit + EXCLUDED.functional_period_credit,
                updated_at               = now()',
            [
                $bucket['entity_id'],
                $bucket['fiscal_period_id'],
                $bucket['account_id'],
                $bucket['cost_centre_id'],
                $bucket['currency_code'],
                $bucket['debit'],
                $bucket['credit'],
                $bucket['functional_debit'],
                $bucket['functional_credit'],
            ]
        );
    }
}
