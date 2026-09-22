<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\JournalHeader;
use App\Models\User;

/**
 * Work that follows a successful posting inside the same transaction: raising the
 * step-8 exceptions of Document B §2.2, opening the advance a TT-07 issues.
 * Observers create records; they never change the posted entry.
 */
interface PostingObserver
{
    public function posted(JournalHeader $header, User $actor): void;
}
