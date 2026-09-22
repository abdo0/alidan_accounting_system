<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\Violation;

/**
 * A check that runs at posting after the validation chain, inside the posting
 * transaction -- duplicate detection (Document B §2.2 step 4) is one.
 */
interface PostingGuard
{
    /** @return list<Violation> */
    public function beforePosting(PostingContext $context): array;
}
