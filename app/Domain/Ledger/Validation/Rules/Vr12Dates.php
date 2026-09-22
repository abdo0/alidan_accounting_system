<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-12: The transaction date is not after the posting date, and the posting date lies in the period. */
final class Vr12Dates extends BaseRule
{
    public function code(): string
    {
        return 'VR-12';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Period;
    }

    public function check(PostingContext $context): array
    {
        $header = $context->header;
        $violations = [];

        if ($header->txn_date !== null && $header->txn_date->greaterThan($header->posting_date)) {
            $violations[] = $this->violation(['txn' => $header->txn_date->toDateString(), 'posting' => $header->posting_date->toDateString()]);
        }

        if (! $context->period()->contains($header->posting_date) || $context->period()->company_id !== $header->company_id) {
            $violations[] = $this->violation(['posting' => $header->posting_date->toDateString(), 'period' => $context->period()->period_code], null, 'period');
        }

        return $violations;
    }
}
