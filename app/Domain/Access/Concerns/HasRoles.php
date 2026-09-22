<?php

declare(strict_types=1);

namespace App\Domain\Access\Concerns;

use App\Domain\Access\Enums\PermissionScope;
use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    /**
     * Resolved roles with their permissions, for the life of this instance.
     *
     * Every authorization question ultimately walks roles -> permissions, and a
     * single request asks many of them. Without this the panel issues two queries
     * per policy check; worse, reading $this->roles as a property throws under
     * Model::shouldBeStrict(), which is on everywhere but production -- so the
     * permission check has to load the relation explicitly rather than touch it.
     *
     * @var Collection<int, Role>|null
     */
    private ?Collection $resolvedRoles = null;

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['granted_by', 'granted_at']);
    }

    /** @return Collection<int, Role> */
    public function resolvedRoles(): Collection
    {
        if ($this->resolvedRoles instanceof Collection) {
            return $this->resolvedRoles;
        }

        $roles = $this->relationLoaded('roles')
            ? $this->getRelation('roles')
            : $this->roles()->get();

        $roles->loadMissing('permissions');

        return $this->resolvedRoles = $roles;
    }

    /**
     * Call after granting or revoking a role on an instance that is still in use --
     * otherwise the memo above keeps answering with the roles held a moment ago.
     */
    public function forgetResolvedRoles(): static
    {
        $this->resolvedRoles = null;
        $this->unsetRelation('roles');

        return $this;
    }

    public function hasRole(string ...$names): bool
    {
        return $this->resolvedRoles()->whereIn('name', $names)->isNotEmpty();
    }

    /** @return Collection<int, string> */
    public function permissionNames(): Collection
    {
        return $this->resolvedRoles()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->permissionNames()->contains($permission);
    }

    /**
     * The widest scope any of this user's roles grants for a permission, or null
     * when none grants it. "Own" limits the user to records they created
     * (Document C tab 19, Accounting Data Entry).
     */
    public function permissionScope(string $permission): ?PermissionScope
    {
        if (! $this->is_active) {
            return null;
        }

        $scopes = $this->resolvedRoles()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->filter(fn (Permission $granted): bool => $granted->name === $permission)
            ->map(fn (Permission $granted): PermissionScope => PermissionScope::from((string) $granted->getRelation('pivot')->getAttribute('scope')));

        if ($scopes->isEmpty()) {
            return null;
        }

        return $scopes->contains(PermissionScope::All) ? PermissionScope::All : $scopes->first();
    }

    /** Whether the user may act on a record created by $creatorId under $permission. */
    public function hasPermissionOver(string $permission, ?int $creatorId): bool
    {
        return match ($this->permissionScope($permission)) {
            PermissionScope::All, PermissionScope::Conditional => true,
            PermissionScope::Own => $creatorId !== null && $creatorId === $this->getKey(),
            null => false,
        };
    }

    /**
     * A read-only role (the auditor) can never write, whatever else it is granted.
     * Expressed here rather than by carefully curating permissions, because the
     * curation is what drifts.
     */
    public function isReadOnly(): bool
    {
        $roles = $this->resolvedRoles();

        return $roles->isNotEmpty()
            && $roles->every(fn (Role $role): bool => $role->is_read_only);
    }

    public function requiresMfa(): bool
    {
        return $this->resolvedRoles()->contains(fn (Role $role): bool => $role->requires_mfa);
    }

    /** Whether an authenticator has been enrolled. Enrolment is optional for every role. */
    public function hasEnrolledMfa(): bool
    {
        return $this->mfa_confirmed_at !== null || filled($this->getAppAuthenticationSecret());
    }

    /**
     * Policies used to refuse posting and approval until MFA was enrolled. Enrolment
     * is now optional, including for the system administrator, so this always passes.
     */
    public function hasSatisfiedMfaRequirement(): bool
    {
        return true;
    }
}
