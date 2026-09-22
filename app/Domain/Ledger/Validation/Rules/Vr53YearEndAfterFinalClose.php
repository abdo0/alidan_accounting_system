<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;

/**
 * VR-53: the year-end result transfer (TT-38) is permitted only after the final
 * close of the last period of the fiscal year, and only into that period. It is
 * the one posting a Final Close period accepts (VR-09 lets it through).
 */
final class Vr53YearEndAfterFinalClose extends BaseRule
{
    public function code(): string
    {
        return 'VR-53';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Period;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-38')) {
            return [];
        }

        $period = $context->period();
        $last = AccountingPeriod::query()->where('fiscal_year_id', $period->fiscal_year_id)->orderByDesc('ends_on')->first();

        return $last?->id === $period->id && $period->status === PeriodStatus::FinalClose ? [] : [$this->violation()];
    }
}
