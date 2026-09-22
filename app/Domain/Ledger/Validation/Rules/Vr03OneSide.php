<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-03: A line carries a debit or a credit, never both, never zero, never negative. */
final class Vr03OneSide extends BaseRule
{
    public function code(): string
    {
        return 'VR-03';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Structural;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return true;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            $valid = $line->debit >= 0 && $line->credit >= 0
                && ! ($line->debit > 0 && $line->credit > 0)
                && ($line->debit + $line->credit) > 0;

            if (! $valid) {
                $violations[] = $this->violation([], $line);
            }
        }

        return $violations;
    }
}
