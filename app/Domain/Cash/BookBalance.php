<?php

declare(strict_types=1);

namespace App\Domain\Cash;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\MasterData\BankAccount;
use DateTimeInterface;

/**
 * Document B §4.8:
 *
 *   book_balance(account, as_at) = Σ debit − Σ credit over posted journal_lines
 *                                  of the account up to as_at
 *
 * Read-only in every layer. There is no "post to the cash book": the cash position
 * is a view of the ledger, not a ledger of its own (VR-57).
 */
final class BookBalance
{
    public function __construct(private readonly LedgerBalance $ledger) {}

    public function at(BankAccount $account, DateTimeInterface $asAt): int
    {
        return $this->ledger->ofAccount($account->account_id, $asAt);
    }

    public function variance(Reconciliation $reconciliation): int
    {
        $account = $reconciliation->bankAccount ?? throw new \LogicException('A cash reconciliation names its cash account.');

        return $reconciliation->actual_balance - $this->at($account, $reconciliation->as_at_date);
    }
}
