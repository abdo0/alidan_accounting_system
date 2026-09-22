<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-08: A counterparty on payable, receivable, advance, shareholder and revenue lines. */
final class Vr08Counterparty extends BaseRule
{
    public function code(): string
    {
        return 'VR-08';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            if ($context->account($line)->requires_counterparty && $line->counterparty_id === null && $line->advance_holder_id === null) {
                $violations[] = $this->violation(['account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
