<?php

declare(strict_types=1);

namespace App\Policies;

/** Never written, never purged, and readable only with audit.view. */
class AuditLogPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'audit.view';
    }
}
