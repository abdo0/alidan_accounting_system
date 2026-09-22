<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-19: Amounts are whole IQD; decimal input is rejected. */
final class Vr19WholeDinars extends BaseRule
{
    public function code(): string
    {
        return 'VR-19';
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
            foreach (['debit', 'credit'] as $side) {
                $raw = (string) ($line->getAttributes()[$side] ?? '0');
                if (preg_match('/^-?\d+(\.0+)?$/', $raw) !== 1) {
                    $violations[] = $this->violation(['amount' => $raw], $line);
                }
            }
        }

        return $violations;
    }
}
