<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/** VR-20: Funding is never revenue, and spending never reduces a shareholder loan. */
final class Vr20FundingIsNotRevenue extends BaseRule
{
    public function code(): string
    {
        return 'VR-20';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];

        if ($context->isType(...self::FUNDING_TYPES)) {
            foreach ($context->creditLines() as $line) {
                if ($context->account($line)->account_type === AccountType::Revenue) {
                    $violations[] = $this->violation([], $line);
                }
            }
        }

        if (! $context->isType(...self::LOAN_REDUCING_TYPES)) {
            foreach ($context->debitLines() as $line) {
                if (in_array($context->account($line)->fs_line_code, self::SHAREHOLDER_LINES, true)) {
                    $violations[] = $this->violation(['account' => $context->account($line)->code], $line, 'loan');
                }
            }
        }

        return $violations;
    }
}
