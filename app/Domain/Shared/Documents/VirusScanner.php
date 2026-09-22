<?php

declare(strict_types=1);

namespace App\Domain\Shared\Documents;

/**
 * Document B §8: attachments are virus-scanned on upload. The scanner itself is an
 * infrastructure decision (ClamAV is the usual one); deployment binds an
 * implementation. NullVirusScanner is bound until it does.
 */
interface VirusScanner
{
    /** @return bool true when the file is clean */
    public function isClean(string $path): bool;
}
