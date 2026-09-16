<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

/**
 * A proposed ledger line. Amounts are strings throughout: they end up in a
 * numeric(20,4) column, and a float round-trip is how a trial balance ends up out
 * by a fraction nobody can find.
 */
final readonly class JournalLineDraft
{
    public function __construct(
        public int $accountId,
        public string $debit = '0',
        public string $credit = '0',
        public ?int $costCentreId = null,
        public ?int $projectId = null,
        public ?string $description = null,
        public ?string $partnerType = null,
        public ?int $partnerId = null,
        public ?int $taxCodeId = null,
        public ?string $taxBaseAmount = null,
        public ?string $quantity = null,
        public ?string $uom = null,
    ) {}

    public static function debit(int $accountId, string $amount, ?int $costCentreId = null, ?string $description = null): self
    {
        return new self(
            accountId: $accountId,
            debit: $amount,
            costCentreId: $costCentreId,
            description: $description,
        );
    }

    public static function credit(int $accountId, string $amount, ?int $costCentreId = null, ?string $description = null): self
    {
        return new self(
            accountId: $accountId,
            credit: $amount,
            costCentreId: $costCentreId,
            description: $description,
        );
    }

    public function withCostCentre(?int $costCentreId): self
    {
        return new self(
            accountId: $this->accountId,
            debit: $this->debit,
            credit: $this->credit,
            costCentreId: $costCentreId,
            projectId: $this->projectId,
            description: $this->description,
            partnerType: $this->partnerType,
            partnerId: $this->partnerId,
            taxCodeId: $this->taxCodeId,
            taxBaseAmount: $this->taxBaseAmount,
            quantity: $this->quantity,
            uom: $this->uom,
        );
    }

    public function isDebit(): bool
    {
        return bccomp($this->debit, '0', 4) > 0;
    }

    public function isCredit(): bool
    {
        return bccomp($this->credit, '0', 4) > 0;
    }

    /** Signed movement, debit positive. */
    public function signedAmount(): string
    {
        return bcsub($this->debit, $this->credit, 4);
    }
}
