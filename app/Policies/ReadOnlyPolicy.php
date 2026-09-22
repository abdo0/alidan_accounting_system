<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * For records that exist but are never written through the UI: the audit trail,
 * the permission catalogue, segregation-of-duty overrides, posted entries.
 * Writing them is not a permission anyone can be granted.
 */
abstract class ReadOnlyPolicy extends BasePolicy
{
    protected function managePermission(): string
    {
        return '__never__';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
