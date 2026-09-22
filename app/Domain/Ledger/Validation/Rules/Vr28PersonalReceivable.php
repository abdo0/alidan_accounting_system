<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-28: A personal receivable names its individual and carries an approval reference. */
final class Vr28PersonalReceivable extends BaseRule
{
    public function code(): string
    {
        return 'VR-28';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            $account = $context->account($line);

            if ($account->fs_line_code !== self::PERSONAL_RECEIVABLE_LINE || $account->holder_counterparty_id === null) {
                continue;
            }

            $named = in_array($account->holder_counterparty_id, [$line->counterparty_id, $line->advance_holder_id], true);

            if (! $named || trim((string) $context->header->approval_ref) === '') {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
