<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/** VR-36: A project-interest acquisition payment is never expensed. */
final class Vr36AcquisitionNotExpensed extends BaseRule
{
    public function code(): string
    {
        return 'VR-36';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-20', 'TT-21')) {
            return [];
        }

        $violations = [];
        foreach ($context->debitLines() as $line) {
            if ($context->account($line)->account_type === AccountType::Expense) {
                $violations[] = $this->violation(['account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
