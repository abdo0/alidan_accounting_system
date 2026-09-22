<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-25: Issuing an advance never debits an expense, CIP or fixed-asset account. */
final class Vr25AdvanceIsAnAsset extends BaseRule
{
    public function code(): string
    {
        return 'VR-25';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-07', 'TT-08', 'TT-14')) {
            return [];
        }

        $violations = [];
        foreach ($context->debitLines() as $line) {
            if (self::isCostOrAsset($context->account($line))) {
                $violations[] = $this->violation(['account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
