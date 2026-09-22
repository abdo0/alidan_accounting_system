<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RolePolicy extends BasePolicy
{
    protected function viewPermission(): string
    {
        return 'users.manage';
    }

    protected function managePermission(): string
    {
        return 'users.manage';
    }

    /** is_system roles are the segregation-of-duties design; they are not editable away. */
    public function delete(User $user, Model $record): bool
    {
        return $record instanceof Role
            && ! $record->is_system
            && $this->withEnrolledMfa($user, 'users.manage');
    }
}
