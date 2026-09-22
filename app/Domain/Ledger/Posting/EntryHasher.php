<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Tamper evidence: each posted entry stores SHA256(previous hash || canonical
 * payload), so an auditor can verify the chain independently.
 *
 * Chained per (entity, journal, fiscal year) -- the same grain as the gapless
 * sequence counter. A single global chain would fork under concurrent posting: two
 * transactions read the same head, both chain from it, and the verification walk
 * breaks permanently and undetectably. Because the sequencer already holds that
 * counter row FOR UPDATE, this grain costs no additional locking.
 *
 * The payload includes the lines and excludes clock values, or re-verification of a
 * historical entry would never reproduce the hash.
 */
final class EntryHasher
{
    /**
     * Computes the chain links without writing them. The caller must persist them in
     * the same statement that marks the entry posted -- after that moment the row is
     * immutable, and a follow-up UPDATE is refused by the database.
     *
     * @return array{prev: string, hash: string}
     */
    public function chain(JournalEntry $entry, int $fiscalYearId): array
    {
        $previous = DB::selectOne(
            'SELECT je.entry_hash
               FROM journal_entries je
               JOIN fiscal_periods fp ON fp.id = je.fiscal_period_id
              WHERE je.entity_id = ?
                AND je.journal_id = ?
                AND fp.fiscal_year_id = ?
                AND je.entry_hash IS NOT NULL
                AND je.id <> ?
              ORDER BY je.id DESC
              LIMIT 1',
            [$entry->entity_id, $entry->journal_id, $fiscalYearId, $entry->id]
        );

        $previousHash = $previous->entry_hash ?? str_repeat('0', 64);

        return [
            'prev' => $previousHash,
            'hash' => hash('sha256', $previousHash.$this->canonicalPayload($entry)),
        ];
    }

    public function canonicalPayload(JournalEntry $entry): string
    {
        $lines = $entry->lines
            ->sortBy('line_no')
            ->map(fn ($line): string => implode(':', [
                $line->line_no,
                $line->account_id,
                $line->cost_centre_id ?? '',
                $line->project_id ?? '',
                $line->currency_code,
                (string) $line->debit_amount,
                (string) $line->credit_amount,
            ]))
            ->implode('|');

        return implode('#', [
            $entry->entity_id,
            $entry->journal_id,
            $entry->fiscal_period_id,
            (string) $entry->entry_no,
            $entry->entry_date->toDateString(),
            $entry->description,
            $entry->currency_code,
            (string) $entry->total_debit,
            (string) $entry->total_credit,
            $lines,
        ]);
    }

    /** Recomputes the chain for verification; used by the integrity report. */
    public function verify(JournalEntry $entry): bool
    {
        $expected = hash('sha256', (string) $entry->prev_entry_hash.$this->canonicalPayload($entry));

        return hash_equals((string) $entry->entry_hash, $expected);
    }
}
