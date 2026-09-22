<?php

declare(strict_types=1);

namespace App\Domain\Controls\Duplicates;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Enums\DuplicateDisposition;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Dispositions a duplicate flag. Needs at least the Review permission
 * (Document B §2.5), and never by the person who made the entry: a maker does not
 * clear the suspicion about their own work.
 */
final class DispositionService
{
    public function disposition(User $actor, DuplicateFlag $flag, DuplicateDisposition $disposition, string $note): DuplicateFlag
    {
        if (! $actor->hasPermission('duplicates.disposition')) {
            throw new AuthorizationException(__('controls.duplicate.not_authorised'));
        }

        $flag->loadMissing('journal');

        if ($flag->journal->created_by === $actor->id) {
            throw RuleViolation::because('VR-16', 'validation_rules.VR-16');
        }

        if (trim($note) === '') {
            throw RuleViolation::because('VR-30', 'controls.duplicate.note_required');
        }

        return DatabaseContext::withAudit('duplicate_disposition', $note, function () use ($actor, $flag, $disposition, $note): DuplicateFlag {
            $flag->forceFill([
                'disposition' => $disposition,
                'dispositioned_by' => $actor->id,
                'dispositioned_at' => now(),
                'disposition_note' => $note,
            ])->save();

            return $flag;
        });
    }
}
