<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-41: Depreciation and amortization run once per period per asset class. */
final class Vr41OneRunPerPeriod extends BaseRule
{
    public function code(): string
    {
        return 'VR-41';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-24', 'TT-26')) {
            return [];
        }

        $classes = $context->lines()->pluck('asset_class')->unique()->values()->all();

        $exists = JournalHeader::query()
            ->whereKeyNot($context->header->id)
            ->where('transaction_type_id', $context->header->transaction_type_id)
            ->where('period_id', $context->header->period_id)
            ->whereIn('status', JournalStatus::ledgerValues())
            ->whereHas('lines', fn ($q) => $q->whereIn('asset_class', array_filter($classes))->orWhereNull('asset_class'))
            ->exists();

        return $exists ? [$this->violation(['period' => $context->period()->period_code])] : [];
    }
}
