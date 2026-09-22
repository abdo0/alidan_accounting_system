<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Access\Role;
use App\Models\User;

trait ActsAsRole
{
    /**
     * A user holding exactly the named seeded roles, with MFA already enrolled so
     * that tests of permission are not accidentally tests of enrolment. Pass
     * mfa: false to exercise the other path.
     */
    protected function userWithRole(string $role, bool $mfa = true, bool $active = true): User
    {
        $user = User::factory()->create([
            'is_active' => $active,
            'mfa_confirmed_at' => $mfa ? now() : null,
        ]);

        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());

        return $user->forgetResolvedRoles();
    }

    /** A user with no role at all: authenticated, but not admitted to the panel. */
    protected function userWithoutRoles(): User
    {
        return User::factory()->create(['is_active' => true]);
    }
}
