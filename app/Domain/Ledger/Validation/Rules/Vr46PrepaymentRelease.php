<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-46: Releasing a prepayment never exceeds its carried balance. */
final class Vr46PrepaymentRelease extends BaseRule
{
    public function code(): string
    {
        return 'VR-46';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-31')) {
            return [];
        }

        $violations = [];
        foreach ($context->creditLines() as $line) {
            $account = $context->account($line);

            if (! self::inRange($account->code, '113001', '113009')) {
                continue;
            }

            $carried = app(LedgerBalance::class)->ofAccount($account->id, $context->header->posting_date);

            if ($line->credit > $carried) {
                $violations[] = $this->violation(['account' => $account->code, 'carried' => $carried], $line);
            }
        }

        return $violations;
    }
}
