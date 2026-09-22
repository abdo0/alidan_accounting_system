<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/** VR-44: a revenue line excluded from the government-share base carries a documented reason. */
final class Vr44ExclusionReason extends BaseRule
{
    public function code(): string
    {
        return 'VR-44';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];

        foreach ($context->lines() as $line) {
            if ($context->account($line)->account_type === AccountType::Revenue && $line->revenue_eligible === false && trim((string) $line->eligibility_reason) === '') {
                $violations[] = $this->violation([], $line);
            }
        }

        return $violations;
    }
}
