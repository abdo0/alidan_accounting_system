<?php

declare(strict_types=1);

namespace App\Domain\Access\Concerns;

use App\Domain\Access\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['granted_by', 'granted_at']);
    }

    public function hasRole(string ...$names): bool
    {
        return $this->roles->whereIn('name', $names)->isNotEmpty();
    }

    /** @return Collection<int, string> */
    public function permissionNames(): Collection
    {
        return $this->roles
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
     * A read-only role (the auditor) can never write, whatever else it is granted.
     * Expressed here rather than by carefully curating permissions, because the
     * curation is what drifts.
     */
    public function isReadOnly(): bool
    {
        return $this->roles->isNotEmpty()
            && $this->roles->every(fn (Role $role): bool => $role->is_read_only);
    }

    public function requiresMfa(): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->requires_mfa);
    }
}
