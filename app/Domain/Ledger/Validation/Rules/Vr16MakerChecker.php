<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-16: Nobody reviews or approves an entry they created. */
final class Vr16MakerChecker extends BaseRule
{
    public function code(): string
    {
        return 'VR-16';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Workflow;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return $checkpoint === Checkpoint::Review || $checkpoint === Checkpoint::Approve;
    }

    public function check(PostingContext $context): array
    {
        return $context->actor->id === $context->header->created_by ? [$this->violation()] : [];
    }
}
