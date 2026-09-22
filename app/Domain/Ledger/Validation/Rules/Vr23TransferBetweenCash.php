<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-23: Both legs of a cash transfer (TT-06) are cash accounts. */
final class Vr23TransferBetweenCash extends BaseRule
{
    public function code(): string
    {
        return 'VR-23';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-06')) {
            return [];
        }

        return $context->lines()->every(fn ($line): bool => $context->account($line)->is_cash_account)
            ? []
            : [$this->violation()];
    }
}
