<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-49: A reclassification never credits an advance account a second time. */
final class Vr49NoSecondAdvanceCredit extends BaseRule
{
    public function code(): string
    {
        return 'VR-49';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-34')) {
            return [];
        }

        $violations = [];
        foreach ($context->creditLines() as $line) {
            if ($context->account($line)->is_advance_account) {
                $violations[] = $this->violation(['account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
