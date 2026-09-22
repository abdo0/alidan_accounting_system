<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-02: At least two lines, at least one debit and one credit. */
final class Vr02TwoSides extends BaseRule
{
    public function code(): string
    {
        return 'VR-02';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Structural;
    }

    public function check(PostingContext $context): array
    {
        $ok = $context->lines()->count() >= 2
            && $context->debitLines()->isNotEmpty()
            && $context->creditLines()->isNotEmpty();

        return $ok ? [] : [$this->violation()];
    }
}
