<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-48: Clearing the suspense account needs a resolution reference. */
final class Vr48SuspenseResolution extends BaseRule
{
    public function code(): string
    {
        return 'VR-48';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-33')) {
            return [];
        }

        return trim((string) $context->header->resolution_ref) === '' ? [$this->violation()] : [];
    }
}
