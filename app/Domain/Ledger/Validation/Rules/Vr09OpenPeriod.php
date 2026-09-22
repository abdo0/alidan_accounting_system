<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Organisation\Enums\PeriodStatus;

/** VR-09: No posting into a Final Close or Locked period; Soft Close only by the Finance Manager with a reason. */
final class Vr09OpenPeriod extends BaseRule
{
    public function code(): string
    {
        return 'VR-09';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Period;
    }

    public function check(PostingContext $context): array
    {
        $status = $context->period()->status;

        // VR-53 governs the year-end transfer, the one posting a Final Close period takes.
        if ($status === PeriodStatus::FinalClose && $context->isType('TT-38')) {
            return [];
        }

        if ($status->isClosedToEveryone()) {
            return [$this->violation(['period' => $context->period()->period_code, 'status' => $status->getLabel()])];
        }

        if ($status === PeriodStatus::SoftClose) {
            $privileged = $context->actor->hasPermission('period.final_close');
            $reason = trim((string) $context->header->soft_close_reason);

            if (! $privileged || $reason === '') {
                return [$this->violation(['period' => $context->period()->period_code], null, 'soft_close')];
            }
        }

        return [];
    }
}
