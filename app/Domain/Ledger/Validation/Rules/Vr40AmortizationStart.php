<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Organisation\ParameterResolver;

/** VR-40: Amortization is never effective before the commercial operation date. */
final class Vr40AmortizationStart extends BaseRule
{
    public function code(): string
    {
        return 'VR-40';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-24')) {
            return [];
        }

        $start = app(ParameterResolver::class)->commercialOperationDate();

        return $start !== null && $context->header->posting_date->lessThan($start)
            ? [$this->violation(['date' => $start->toDateString()])]
            : [];
    }
}
