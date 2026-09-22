<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Organisation\ParameterResolver;

/** VR-39: CIP transfer and amortization are blocked until the commercial operation date is set. */
final class Vr39CommercialOperation extends BaseRule
{
    public function code(): string
    {
        return 'VR-39';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-23', 'TT-24')) {
            return [];
        }

        return app(ParameterResolver::class)->commercialOperationDate() === null ? [$this->violation()] : [];
    }
}
