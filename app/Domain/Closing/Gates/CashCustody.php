<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Reconciliation;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\MasterData\BankAccount;
use App\Domain\Organisation\AccountingPeriod;
use Illuminate\Support\Facades\DB;

/**
 * RE-18: every cash account that moved in the period has a custodian and a
 * responsible accountant on record, and an approved count or reconciliation
 * dated inside the period.
 */
final class CashCustody implements CloseGate
{
    public function blockers(AccountingPeriod $period): array
    {
        $moved = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jh.period_id', $period->id)
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->whereNotNull('jl.cash_account_id')
            ->distinct()
            ->pluck('jl.cash_account_id');

        $blockers = [];

        foreach (BankAccount::query()->whereIn('id', $moved)->get() as $account) {
            if ($account->custodian_id === null || $account->responsible_user_id === null) {
                $blockers[] = __('closing.gates.custodian', ['account' => $account->code]);
            }

            $counted = Reconciliation::query()
                ->where('bank_account_id', $account->id)
                ->where('status', ReconciliationStatus::Approved)
                ->whereBetween('as_at_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])
                ->exists();

            if (! $counted) {
                $blockers[] = __('closing.gates.count', ['account' => $account->code]);
            }
        }

        return $blockers;
    }
}
