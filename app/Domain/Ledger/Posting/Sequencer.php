<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use Illuminate\Support\Facades\DB;

/**
 * Gapless document numbering, for journal entries and subledger documents alike.
 *
 * The counter row is locked FOR UPDATE, which serialises concurrent posts to the same
 * journal. That is correct but it means the lock must be taken as LATE as possible --
 * after validation, immediately before the writes -- or a month-end batch turns into
 * a queue. Nothing fallible may run after the number is drawn, because a rollback
 * would burn it and leave the gap this exists to prevent.
 */
final class Sequencer
{
    public function next(string $scope, string $scopeKey, string $prefix = '', int $padTo = 5): string
    {
        $row = DB::selectOne(
            'SELECT next_value, prefix, pad_to FROM sequence_counters
             WHERE scope = ? AND scope_key = ? FOR UPDATE',
            [$scope, $scopeKey]
        );

        if ($row === null) {
            // Two concurrent first-posts would both find nothing and both insert, so
            // the insert must tolerate the race rather than assume it away.
            DB::insert(
                'INSERT INTO sequence_counters (scope, scope_key, next_value, prefix, pad_to, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?, now(), now())
                 ON CONFLICT (scope, scope_key) DO NOTHING',
                [$scope, $scopeKey, $prefix, $padTo]
            );

            $row = DB::selectOne(
                'SELECT next_value, prefix, pad_to FROM sequence_counters
                 WHERE scope = ? AND scope_key = ? FOR UPDATE',
                [$scope, $scopeKey]
            );
        }

        $value = (int) $row->next_value;
        $usePrefix = (string) ($row->prefix ?? $prefix);
        $usePad = (int) ($row->pad_to ?? $padTo);

        DB::update(
            'UPDATE sequence_counters SET next_value = next_value + 1, updated_at = now()
             WHERE scope = ? AND scope_key = ?',
            [$scope, $scopeKey]
        );

        return $usePrefix.str_pad((string) $value, $usePad, '0', STR_PAD_LEFT);
    }

    public function peek(string $scope, string $scopeKey): int
    {
        $row = DB::selectOne(
            'SELECT next_value FROM sequence_counters WHERE scope = ? AND scope_key = ?',
            [$scope, $scopeKey]
        );

        return $row === null ? 1 : (int) $row->next_value;
    }
}
