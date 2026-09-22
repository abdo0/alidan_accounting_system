<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-38: A non-posting parent (115030, 112100) is never posted to; its children are. */
final class Vr38NonPostingParent extends BaseRule
{
    public function code(): string
    {
        return 'VR-38';
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

            if (! $account->is_posting && ! $account->is_group) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
