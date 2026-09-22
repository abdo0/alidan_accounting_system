<?php

declare(strict_types=1);

namespace App\Domain\Controls\Duplicates;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Ledger\Posting\PostingGuard;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\Violation;

/**
 * VR-30 at posting (Document B §2.2 step 4): the tests run again, and any flag not
 * dispositioned "Not a Duplicate" stops the posting.
 */
final class DuplicateGuard implements PostingGuard
{
    public function __construct(private readonly DuplicateDetector $detector) {}

    public function beforePosting(PostingContext $context): array
    {
        return $this->detector->scan($context->header)
            ->reject(fn (DuplicateFlag $flag): bool => $flag->disposition?->releasesPosting() === true)
            ->map(fn (DuplicateFlag $flag): Violation => new Violation(
                'VR-30',
                Severity::Blocking,
                __('validation_rules.VR-30', ['reason' => $flag->match_reason]),
            ))
            ->values()
            ->all();
    }
}
