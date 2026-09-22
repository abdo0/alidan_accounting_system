<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-17: Only an Approved entry is posted, and only by a holder of the Post permission. */
final class Vr17PostPermission extends BaseRule
{
    public function code(): string
    {
        return 'VR-17';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Workflow;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return $checkpoint === Checkpoint::Post;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];

        if (! $context->actor->hasPermission('journal.post')) {
            $violations[] = $this->violation([], null, 'permission');
        }

        if ($context->header->status !== JournalStatus::Approved && ! $context->type()->is_system) {
            $violations[] = $this->violation(['status' => $context->header->status->getLabel()]);
        }

        return $violations;
    }
}
