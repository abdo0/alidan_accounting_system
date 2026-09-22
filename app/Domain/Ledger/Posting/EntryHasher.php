<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * Tamper evidence: each posted entry stores SHA256(previous hash || canonical
 * payload), chained per company and fiscal year -- the grain of the JV sequence,
 * whose counter row is already locked while posting, so the chain cannot fork.
 *
 * The payload includes the lines and excludes clock values, so re-verification of
 * a historical entry reproduces the hash.
 */
final class EntryHasher
{
    /** @return array{prev: string, hash: string} */
    public function chain(JournalHeader $header, string $jvNo): array
    {
        $previous = DB::selectOne(
            'SELECT jh.entry_hash
               FROM journal_headers jh
               JOIN accounting_periods ap ON ap.id = jh.period_id
              WHERE jh.company_id = ?
                AND ap.fiscal_year_id = ?
                AND jh.entry_hash IS NOT NULL
                AND jh.id <> ?
              ORDER BY jh.posted_at DESC, jh.id DESC
              LIMIT 1',
            [$header->company_id, $header->period->fiscal_year_id, $header->id],
        );

        $previousHash = $previous->entry_hash ?? str_repeat('0', 64);

        return [
            'prev' => $previousHash,
            'hash' => hash('sha256', $previousHash.$this->canonicalPayload($header, $jvNo)),
        ];
    }

    public function canonicalPayload(JournalHeader $header, ?string $jvNo = null): string
    {
        $lines = $header->lines
            ->sortBy('line_no')
            ->map(fn (JournalLine $line): string => implode(':', [
                $line->line_no,
                $line->account_id,
                $line->project_id,
                $line->cost_center_id ?? '',
                $line->counterparty_id ?? '',
                $line->debit,
                $line->credit,
            ]))
            ->implode('|');

        return implode('#', [
            $header->company_id,
            $jvNo ?? $header->jv_no,
            $header->transaction_type_id,
            $header->posting_date->toDateString(),
            $header->txn_date?->toDateString() ?? '',
            $header->description_ar,
            $header->source_reference ?? '',
            $lines,
        ]);
    }

    public function verify(JournalHeader $header): bool
    {
        $expected = hash('sha256', (string) $header->prev_entry_hash.$this->canonicalPayload($header));

        return hash_equals((string) $header->entry_hash, $expected);
    }
}
