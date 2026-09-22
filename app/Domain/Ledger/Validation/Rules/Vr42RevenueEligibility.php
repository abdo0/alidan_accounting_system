<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/** VR-42: Every revenue line carries a government-share eligibility flag. */
final class Vr42RevenueEligibility extends BaseRule
{
    public function code(): string
    {
        return 'VR-42';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            if ($context->account($line)->account_type === AccountType::Revenue && $line->revenue_eligible === null) {
                $violations[] = $this->violation([], $line);
            }
        }

        return $violations;
    }
}
