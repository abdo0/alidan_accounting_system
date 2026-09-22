<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Shareholder;

/** VR-21: Only the shareholder who owns a 221xxx / 222xxx account may be credited to it; a third-party funder goes to 211090. */
final class Vr21ShareholderClaim extends BaseRule
{
    public function code(): string
    {
        return 'VR-21';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];

        foreach ($context->creditLines() as $line) {
            $account = $context->account($line);

            if (! in_array($account->fs_line_code, self::SHAREHOLDER_LINES, true)) {
                continue;
            }

            $owner = Shareholder::query()
                ->where('loan_account_id', $account->id)
                ->orWhere('current_account_id', $account->id)
                ->value('counterparty_id');

            if ($owner === null || $line->counterparty_id !== $owner) {
                $violations[] = $this->violation(['account' => $account->code], $line);
            }
        }

        return $violations;
    }
}
