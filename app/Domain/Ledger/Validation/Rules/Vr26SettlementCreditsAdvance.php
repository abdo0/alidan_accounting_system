<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-26: Settling an advance credits the advance account. */
final class Vr26SettlementCreditsAdvance extends BaseRule
{
    public function code(): string
    {
        return 'VR-26';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType(...self::SETTLEMENT_TYPES)) {
            return [];
        }

        return $context->creditLines()->contains(fn ($line): bool => $context->account($line)->is_advance_account)
            ? []
            : [$this->violation()];
    }
}
