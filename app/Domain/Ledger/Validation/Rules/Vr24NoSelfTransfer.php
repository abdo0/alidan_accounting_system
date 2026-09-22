<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-24: A cash transfer never moves money from an account to itself. */
final class Vr24NoSelfTransfer extends BaseRule
{
    public function code(): string
    {
        return 'VR-24';
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

        $debited = $context->debitLines()->pluck('account_id')->all();
        $credited = $context->creditLines()->pluck('account_id')->all();

        return array_intersect($debited, $credited) === [] ? [] : [$this->violation()];
    }
}
