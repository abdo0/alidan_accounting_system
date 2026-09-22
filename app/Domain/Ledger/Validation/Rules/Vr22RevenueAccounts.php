<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/** VR-22: Revenue is credited only to 410001-410009. */
final class Vr22RevenueAccounts extends BaseRule
{
    public function code(): string
    {
        return 'VR-22';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->creditLines() as $line) {
            $account = $context->account($line);

            if ($account->account_type === AccountType::Revenue && ! self::inRange($account->code, '410001', '410009')) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
