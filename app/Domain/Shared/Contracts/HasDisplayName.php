<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * A record that can name itself in the reader's language.
 *
 * The HasTranslatableName trait supplies the implementation; this interface exists
 * so callers can ask for the behaviour by type instead of inspecting class_uses().
 */
interface HasDisplayName
{
    public function displayName(): string;
}
