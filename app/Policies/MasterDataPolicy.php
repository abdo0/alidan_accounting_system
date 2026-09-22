<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Projects, cost centres, counterparties, bank accounts, funding reference data and
 * contracts. Senior Accountant and Finance Manager maintain them (Document C tab 19,
 * "Modify Master Data"); nothing is deleted -- a record in use is deactivated.
 */
class MasterDataPolicy extends BasePolicy
{
    protected function viewPermission(): string
    {
        return 'masterdata.view';
    }

    protected function managePermission(): string
    {
        return 'masterdata.modify';
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
