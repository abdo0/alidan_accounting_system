<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Ledger\JournalHeader;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Viewing and drafting. Every later step is a workflow action whose permission the
 * domain service checks itself; this policy only decides who sees the entry and
 * who may open the draft form.
 */
class JournalHeaderPolicy extends BasePolicy
{
    protected function viewPermission(): string
    {
        return 'journal.view';
    }

    protected function managePermission(): string
    {
        return 'journal.create';
    }

    public function view(User $user, Model $record): bool
    {
        return $record instanceof JournalHeader
            && ($user->hasPermissionOver('journal.view', $record->created_by) || $record->status->isInLedger());
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof JournalHeader
            && $record->isEditable()
            && $user->hasPermissionOver('journal.edit_draft', $record->created_by);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }
}
