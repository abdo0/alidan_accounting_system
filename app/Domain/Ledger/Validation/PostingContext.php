<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\PostingRule;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Account;
use App\Domain\Organisation\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What every rule needs to judge an entry: the header with its lines and their
 * accounts, the transaction type, the resolved posting rule, the period, the acting
 * user and the moment in the workflow.
 */
final class PostingContext
{
    public ?PostingRule $postingRule = null;

    /** @var list<string>|null */
    private ?array $originalDebitCodes = null;

    public function __construct(
        public readonly JournalHeader $header,
        public readonly User $actor,
        public readonly Checkpoint $checkpoint,
    ) {
        $header->loadMissing(['lines.account', 'transactionType', 'period', 'lines.cashAccount', 'lines.counterparty.shareholder']);
    }

    /** @return Collection<int, JournalLine> */
    public function lines(): Collection
    {
        return $this->header->lines;
    }

    public function type(): TransactionType
    {
        return $this->header->transactionType;
    }

    public function period(): AccountingPeriod
    {
        return $this->header->period;
    }

    public function account(JournalLine $line): Account
    {
        return $line->account;
    }

    public function isType(string ...$codes): bool
    {
        return $this->type()->isCode(...$codes);
    }

    /** @return Collection<int, JournalLine> */
    public function debitLines(): Collection
    {
        return $this->lines()->filter(fn (JournalLine $line): bool => $line->debit > 0)->values();
    }

    /** @return Collection<int, JournalLine> */
    public function creditLines(): Collection
    {
        return $this->lines()->filter(fn (JournalLine $line): bool => $line->credit > 0)->values();
    }

    /**
     * The accounts debited on the entry this one reclassifies (VR-49, @original_debit).
     *
     * @return list<string>
     */
    public function originalDebitCodes(): array
    {
        if ($this->originalDebitCodes !== null) {
            return $this->originalDebitCodes;
        }

        $linked = $this->header->linkedJournal()->with('lines.account')->first();

        return $this->originalDebitCodes = $linked === null
            ? []
            : $linked->lines->filter(fn (JournalLine $l): bool => $l->debit > 0)->map(fn (JournalLine $l): string => $l->account->code)->values()->all();
    }
}
