<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-50: A reclassification changes only the account: amount, date, description and source reference stay those of the original. */
final class Vr50ReclassificationUnchanged extends BaseRule
{
    public function code(): string
    {
        return 'VR-50';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-34')) {
            return [];
        }

        $original = $context->header->linkedJournal()->with('lines')->first();

        if ($original === null) {
            return [$this->violation([], null, 'link')];
        }

        $header = $context->header;

        // The amount is that of the original line being moved: each credit here must
        // match a debit of the original on the same account.
        $originalDebits = $original->lines->filter(fn ($line): bool => $line->debit > 0)
            ->map(fn ($line): string => $line->account_id.':'.$line->debit)->all();
        $amountsMatch = $context->creditLines()->every(fn ($line): bool => in_array($line->account_id.':'.$line->credit, $originalDebits, true));

        $same = $amountsMatch
            && $header->txn_date?->toDateString() === $original->txn_date?->toDateString()
            && $header->description_ar === $original->description_ar
            && $header->source_reference === $original->source_reference;

        return $same ? [] : [$this->violation(['jv' => $original->reference()])];
    }
}
