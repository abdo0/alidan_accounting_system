<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/**
 * BRD: a transaction type whose approval path requires the board (TT-20, TT-21,
 * TT-23, TT-34, TT-36, TT-37 -- Document C tabs 12 and 19) is approved only with the
 * board decision's reference.
 */
final class BoardApproval extends BaseRule
{
    public function code(): string
    {
        return 'BRD';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Workflow;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return $checkpoint === Checkpoint::Approve || $checkpoint === Checkpoint::Post;
    }

    public function check(PostingContext $context): array
    {
        return $context->type()->requires_board_approval && trim((string) $context->header->approval_ref) === ''
            ? [$this->violation(['type' => $context->type()->code])]
            : [];
    }
}
