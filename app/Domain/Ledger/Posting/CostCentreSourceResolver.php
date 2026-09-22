<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

/**
 * Step 3 of the defaulting cascade. Each subledger registers its own implementation
 * (fixed asset -> the asset's centre, payroll -> the employee's, AR/AP -> the
 * customer's or vendor's default) rather than the resolver growing a match statement
 * that has to be edited for every module.
 */
interface CostCentreSourceResolver
{
    public function supports(string $sourceType): bool;

    public function resolve(JournalEntryDraft $draft, JournalLineDraft $line): ?int;
}
