<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use Carbon\CarbonImmutable;

/**
 * The single interface between every subledger and the ledger. Nothing else may
 * insert into journal_lines: AR does not know what a customer owes, it knows about
 * invoices, and the balance is the control account.
 */
final readonly class JournalEntryDraft
{
    /** @param  list<JournalLineDraft>  $lines */
    public function __construct(
        public int $entityId,
        public string $journalCode,
        public CarbonImmutable $entryDate,
        public string $description,
        public array $lines,
        public string $sourceType = 'manual',
        public ?int $sourceId = null,
        public ?string $sourceDocumentNo = null,
        public ?CarbonImmutable $sourceDocumentDate = null,
        public ?string $descriptionAr = null,
        public ?CarbonImmutable $postingDate = null,
        public string $currencyCode = 'IQD',
        public ?int $costCentreId = null,
        public ?int $projectId = null,
        /** Document-level activity classification, defaulted onto each line. */
        public ?string $activityType = null,
        public ?string $activityNature = null,
        public bool $isAdjusting = false,
        public bool $isClosing = false,
        public bool $isOpening = false,
        /** System-generated entries have no human maker and are approval-exempt. */
        public bool $isSystemGenerated = false,
        public ?int $reversesEntryId = null,
        public ?string $reversalReason = null,
        public ?CarbonImmutable $autoReverseOn = null,
        public ?string $idempotencyKey = null,
    ) {}

    public function totalDebit(): string
    {
        return array_reduce(
            $this->lines,
            fn (string $carry, JournalLineDraft $line): string => bcadd($carry, $line->debit, 4),
            '0'
        );
    }

    public function totalCredit(): string
    {
        return array_reduce(
            $this->lines,
            fn (string $carry, JournalLineDraft $line): string => bcadd($carry, $line->credit, 4),
            '0'
        );
    }

    public function isBalanced(): bool
    {
        return bccomp($this->totalDebit(), $this->totalCredit(), 4) === 0;
    }

    public function effectivePostingDate(): CarbonImmutable
    {
        return $this->postingDate ?? $this->entryDate;
    }

    /** @param  list<JournalLineDraft>  $lines */
    public function withLines(array $lines): self
    {
        return new self(
            entityId: $this->entityId,
            journalCode: $this->journalCode,
            entryDate: $this->entryDate,
            description: $this->description,
            lines: $lines,
            sourceType: $this->sourceType,
            sourceId: $this->sourceId,
            sourceDocumentNo: $this->sourceDocumentNo,
            sourceDocumentDate: $this->sourceDocumentDate,
            descriptionAr: $this->descriptionAr,
            postingDate: $this->postingDate,
            currencyCode: $this->currencyCode,
            costCentreId: $this->costCentreId,
            projectId: $this->projectId,
            activityType: $this->activityType,
            activityNature: $this->activityNature,
            isAdjusting: $this->isAdjusting,
            isClosing: $this->isClosing,
            isOpening: $this->isOpening,
            isSystemGenerated: $this->isSystemGenerated,
            reversesEntryId: $this->reversesEntryId,
            reversalReason: $this->reversalReason,
            autoReverseOn: $this->autoReverseOn,
            idempotencyKey: $this->idempotencyKey,
        );
    }
}
