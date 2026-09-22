<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-14: A document reference when the status is Complete. */
final class Vr14DocumentReference extends BaseRule
{
    public function code(): string
    {
        return 'VR-14';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Document;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return true;
    }

    public function check(PostingContext $context): array
    {
        return $context->header->doc_status === DocStatus::Complete && trim((string) $context->header->doc_ref) === ''
            ? [$this->violation()]
            : [];
    }
}
