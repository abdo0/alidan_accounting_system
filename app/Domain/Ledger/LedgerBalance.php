<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Enums\JournalStatus;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Balances read straight from journal_lines -- there is no balance table to read
 * instead (Document B §1.1). Debit-positive: an asset's balance is positive, a
 * liability's negative.
 */
final class LedgerBalance
{
    public function ofAccount(int $accountId, ?DateTimeInterface $asAt = null): int
    {
        $query = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jl.account_id', $accountId)
            ->whereIn('jh.status', JournalStatus::ledgerValues());

        if ($asAt !== null) {
            $query->where('jh.posting_date', '<=', $asAt->format('Y-m-d'));
        }

        return (int) $query->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS net')->value('net');
    }
}
