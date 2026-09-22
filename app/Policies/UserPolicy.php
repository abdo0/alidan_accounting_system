<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    protected function viewPermission(): string
    {
        return 'users.manage';
    }

    protected function managePermission(): string
    {
        return 'users.manage';
    }

    /**
     * Users are deactivated, never deleted: their id is the actor on every audit row
     * and the created_by/approved_by of every entry they touched.
     */
    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    /** Granting roles is the sharpest privilege in the system; it needs the second factor. */
    public function manageRoles(User $user, User $subject): bool
    {
        return $this->withEnrolledMfa($user, 'users.manage');
    }
}
