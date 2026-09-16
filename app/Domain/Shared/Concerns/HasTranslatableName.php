<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

/**
 * Master data carries name and name_ar throughout; this resolves whichever the
 * current interface language calls for, falling back rather than showing a blank.
 */
trait HasTranslatableName
{
    public function displayName(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->name_ar ?? null)) {
            return (string) $this->name_ar;
        }

        return (string) $this->name;
    }
}
