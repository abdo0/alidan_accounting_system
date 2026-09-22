<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-04: Posting only to accounts flagged Posting and Active. */
final class Vr04PostingAccount extends BaseRule
{
    public function code(): string
    {
        return 'VR-04';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Account;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            $account = $context->account($line);

            // A non-posting parent gets the more specific VR-38 message.
            if ($account->is_posting && ! $account->is_group && ! $account->is_active) {
                $violations[] = $this->violation(['account' => $account->code], $line, 'inactive');
            } elseif ($account->is_group) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
