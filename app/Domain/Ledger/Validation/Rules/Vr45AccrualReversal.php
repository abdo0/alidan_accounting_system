<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Ledger\Validation\Severity;

/** VR-45: An accrual carries a reversal period or a settlement plan. */
final class Vr45AccrualReversal extends BaseRule
{
    public function code(): string
    {
        return 'VR-45';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function check(PostingContext $context): array
    {
        return $context->isType('TT-30') ? [$this->violation()] : [];
    }
}
