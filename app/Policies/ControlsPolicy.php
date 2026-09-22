<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Exceptions and duplicate flags are raised by the engine, never typed in, and never
 * deleted. Their lifecycle steps are actions whose permissions the services check.
 */
class ControlsPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'exceptions.view';
    }
}
