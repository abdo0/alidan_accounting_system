<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Parameters are never edited in place. A change is a new effective-dated row made
 * through ParameterService, by the Finance Manager only (Document C tab 19).
 */
class ParameterPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'parameters.view';
    }

    public function change(User $user): bool
    {
        return $this->withEnrolledMfa($user, 'parameters.manage');
    }
}
