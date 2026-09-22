<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every policy in this application is two permission strings and the exceptions to
 * them. The strings are the ones AccessControlSeeder already seeds -- policies name
 * permissions, never roles, so adding a role is a seeder change and not a code one.
 *
 * Read-only enforcement is NOT here. It is a single Gate::before in
 * AuthServiceProvider, so that a policy cannot forget it by omission.
 */
abstract class BasePolicy
{
    /** The permission that lets a user see these records at all. */
    abstract protected function viewPermission(): string;

    /** The permission that lets a user change them. */
    abstract protected function managePermission(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->viewPermission());
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->managePermission());
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    /**
     * Anything that changes the ledger or the controls around it needs the second
     * factor actually enrolled, not merely demanded by the role. docs/02 §2.6.
     */
    protected function withEnrolledMfa(User $user, string $permission): bool
    {
        return $user->hasPermission($permission) && $user->hasSatisfiedMfaRequirement();
    }
}
