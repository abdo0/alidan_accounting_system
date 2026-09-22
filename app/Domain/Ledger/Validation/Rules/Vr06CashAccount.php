<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-06: Every line on a 111xxx account carries its cash / bank account, and only such lines do. */
final class Vr06CashAccount extends BaseRule
{
    public function code(): string
    {
        return 'VR-06';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            $account = $context->account($line);

            if ($account->is_cash_account && $line->cash_account_id === null) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            } elseif ($line->cash_account_id !== null && $line->cashAccount?->account_id !== $account->id) {
                $violations[] = $this->violation(['account' => $account->code], $line, 'mismatch');
            }
        }

        return $violations;
    }
}
