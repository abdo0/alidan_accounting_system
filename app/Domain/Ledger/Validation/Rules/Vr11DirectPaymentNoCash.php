<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-11: A direct payment by a shareholder (TT-02) never touches a 111xxx cash account. */
final class Vr11DirectPaymentNoCash extends BaseRule
{
    public function code(): string
    {
        return 'VR-11';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-02')) {
            return [];
        }

        return $context->lines()->contains(fn ($line): bool => $context->account($line)->is_cash_account)
            ? [$this->violation()]
            : [];
    }
}
