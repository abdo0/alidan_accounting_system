<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\MasterData\ChangeRequest;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Models\User;

class ChangeRequestPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'coa.view';
    }

    /** Maker-checker: the proposer never decides their own request. */
    public function decide(User $user, ChangeRequest $request): bool
    {
        return $request->status === ChangeRequestStatus::Proposed
            && $request->proposed_by !== $user->id
            && $this->withEnrolledMfa($user, 'coa.approve');
    }
}
