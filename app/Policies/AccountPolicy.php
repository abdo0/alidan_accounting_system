<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The chart is loaded from the authoritative workbook and never regenerated
 * (Document B §4.1). It is not edited in place: a change is proposed and approved
 * by someone else (VR-60), and an account is deactivated, never deleted.
 */
class AccountPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'coa.view';
    }

    public function proposeChange(User $user, Model $account): bool
    {
        return $user->hasPermission('coa.propose');
    }
}
