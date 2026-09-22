<?php

declare(strict_types=1);

namespace App\Domain\Shared\Documents;

/** Accepts everything. Bound only until deployment provides a real scanner. */
final class NullVirusScanner implements VirusScanner
{
    public function isClean(string $path): bool
    {
        return true;
    }
}
