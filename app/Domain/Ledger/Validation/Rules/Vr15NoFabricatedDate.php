<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-15: An undated entry keeps its date status; a date is never substituted. */
final class Vr15NoFabricatedDate extends BaseRule
{
    public function code(): string
    {
        return 'VR-15';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Date;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return true;
    }

    public function check(PostingContext $context): array
    {
        $header = $context->header;

        if ($header->txn_date === null && $header->date_status === DateStatus::Ok) {
            return [$this->violation()];
        }

        if ($header->txn_date !== null && $header->date_status === DateStatus::NoDateInSource) {
            return [$this->violation([], null, 'substituted')];
        }

        return [];
    }
}
