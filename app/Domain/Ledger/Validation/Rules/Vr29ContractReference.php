<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterResolver;

/** VR-29: Contractor advance, certification, retention and payment entries above the threshold carry a contract. */
final class Vr29ContractReference extends BaseRule
{
    public function code(): string
    {
        return 'VR-29';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-14', 'TT-15', 'TT-16', 'TT-17', 'TT-18')) {
            return [];
        }

        // While no threshold is approved, every such entry needs its contract.
        $threshold = app(ParameterResolver::class)->integer(ParameterCode::ContractThresholdVr29, $context->header->posting_date) ?? 0;

        if ($context->header->totalDebit() < $threshold) {
            return [];
        }

        return $context->lines()->contains(fn ($line): bool => $line->contract_id !== null) ? [] : [$this->violation()];
    }
}
