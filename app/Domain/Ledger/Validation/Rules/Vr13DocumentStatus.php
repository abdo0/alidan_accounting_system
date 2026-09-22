<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Ledger\Validation\Severity;

/** VR-13: Document status Partial or Missing is acknowledged and raises an open exception. */
final class Vr13DocumentStatus extends BaseRule
{
    public function code(): string
    {
        return 'VR-13';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Document;
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function check(PostingContext $context): array
    {
        return $context->header->doc_status === DocStatus::Complete
            ? []
            : [$this->violation(['status' => $context->header->doc_status->getLabel()])];
    }
}
