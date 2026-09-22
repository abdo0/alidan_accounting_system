<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-27: No expense, CIP, fixed-asset or receivable account is credited to settle an advance. */
final class Vr27NoCostCredited extends BaseRule
{
    public function code(): string
    {
        return 'VR-27';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $settles = $context->isType(...self::SETTLEMENT_TYPES)
            || $context->debitLines()->contains(fn ($line): bool => $context->account($line)->is_advance_account && ! $context->isType('TT-35'));

        if (! $settles || $context->isType('TT-35', 'TT-39')) {
            return [];
        }

        $violations = [];
        foreach ($context->creditLines() as $line) {
            $account = $context->account($line);

            if (self::isCostOrAsset($account) || self::isReceivable($account)) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
