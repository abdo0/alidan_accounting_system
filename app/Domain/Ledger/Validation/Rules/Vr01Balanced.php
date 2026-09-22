<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-01: Total debit must equal total credit. */
final class Vr01Balanced extends BaseRule
{
    public function code(): string
    {
        return 'VR-01';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Structural;
    }

    public function check(PostingContext $context): array
    {
        $debit = $context->lines()->sum('debit');
        $credit = $context->lines()->sum('credit');

        return $debit === $credit ? [] : [$this->violation(['debit' => $debit, 'credit' => $credit])];
    }
}
