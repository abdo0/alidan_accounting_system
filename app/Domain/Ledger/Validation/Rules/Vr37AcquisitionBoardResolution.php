<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-37: Completing an acquisition (TT-21) needs a board resolution reference. */
final class Vr37AcquisitionBoardResolution extends BaseRule
{
    public function code(): string
    {
        return 'VR-37';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        return $context->isType('TT-21') && trim((string) $context->header->approval_ref) === ''
            ? [$this->violation()]
            : [];
    }
}
