<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-07: A line on an advance account carries the advance holder or counterparty. */
final class Vr07AdvanceHolder extends BaseRule
{
    public function code(): string
    {
        return 'VR-07';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            if ($context->account($line)->requires_advance_holder && $line->advance_holder_id === null && $line->counterparty_id === null) {
                $violations[] = $this->violation(['account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
