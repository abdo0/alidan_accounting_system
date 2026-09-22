<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-35: The same supplier invoice is not recognised twice for the same counterparty. */
final class Vr35InvoiceOnce extends BaseRule
{
    public function code(): string
    {
        return 'VR-35';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-19') || trim((string) $context->header->doc_ref) === '') {
            return [];
        }

        $counterparties = $context->lines()->pluck('counterparty_id')->filter()->unique()->all();

        $exists = JournalHeader::query()
            ->whereKeyNot($context->header->id)
            ->where('transaction_type_id', $context->header->transaction_type_id)
            ->where('doc_ref', $context->header->doc_ref)
            ->where('status', '!=', 'draft')
            ->whereHas('lines', fn ($q) => $q->whereIn('counterparty_id', $counterparties))
            ->exists();

        return $exists ? [$this->violation(['ref' => $context->header->doc_ref])] : [];
    }
}
