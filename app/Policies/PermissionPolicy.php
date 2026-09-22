<?php

declare(strict_types=1);

namespace App\Policies;

/** The permission catalogue is code, not data. It is shown, never edited. */
class PermissionPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'users.manage';
    }
}
