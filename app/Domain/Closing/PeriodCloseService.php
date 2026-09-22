<?php

declare(strict_types=1);

namespace App\Domain\Closing;

use App\Domain\Closing\Gates\CloseGate;
use App\Domain\Ledger\Enums\ApprovalAction;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;
use App\Domain\Shared\Approval;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Period status transitions (Document B §4.11):
 *
 *   Open --soft close--> Soft Close --final close--> Final Close --lock--> Locked
 *     ^                      |                          |
 *     +---- reopen (FM) -----+------- reopen (FM) ------+
 *
 * The period row is locked FOR UPDATE and the gates re-evaluated under the lock, so
 * a posting cannot slip in between the check and the close. Reopening is a
 * high-risk action and needs a reason. Leaving Locked is not possible here at all.
 */
final class PeriodCloseService
{
    /** @param  iterable<CloseGate>  $gates */
    public function __construct(private readonly iterable $gates = []) {}

    public function softClose(User $actor, AccountingPeriod $period): AccountingPeriod
    {
        return $this->transition($actor, $period, 'period.soft_close', [PeriodStatus::Open], PeriodStatus::SoftClose, ApprovalAction::SoftClose, [
            'soft_closed_by' => $actor->id, 'soft_closed_at' => now(),
        ]);
    }

    public function finalClose(User $actor, AccountingPeriod $period): AccountingPeriod
    {
        return $this->transition($actor, $period, 'period.final_close', [PeriodStatus::Open, PeriodStatus::SoftClose], PeriodStatus::FinalClose, ApprovalAction::FinalClose, [
            'final_closed_by' => $actor->id, 'final_closed_at' => now(),
        ], gated: true);
    }

    public function reopen(User $actor, AccountingPeriod $period, string $reason): AccountingPeriod
    {
        if (trim($reason) === '') {
            throw RuleViolation::because('M15', 'closing.reason_required');
        }

        return $this->transition($actor, $period, 'period.reopen', [PeriodStatus::SoftClose, PeriodStatus::FinalClose], PeriodStatus::Open, ApprovalAction::Reopen, [
            'reopened_by' => $actor->id, 'reopened_at' => now(), 'reopen_reason' => $reason,
        ], reason: $reason, action: 'period_reopen');
    }

    public function lock(User $actor, AccountingPeriod $period): AccountingPeriod
    {
        return $this->transition($actor, $period, 'period.lock', [PeriodStatus::FinalClose], PeriodStatus::Locked, ApprovalAction::Lock, [
            'locked_by' => $actor->id, 'locked_at' => now(),
        ]);
    }

    /** @return list<string> every reason the period cannot be finally closed now */
    public function blockers(AccountingPeriod $period): array
    {
        $blockers = [];

        foreach ($this->gates as $gate) {
            $blockers = [...$blockers, ...$gate->blockers($period)];
        }

        return $blockers;
    }

    /**
     * @param  list<PeriodStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function transition(User $actor, AccountingPeriod $period, string $permission, array $from, PeriodStatus $to, ApprovalAction $approval, array $attributes, bool $gated = false, ?string $reason = null, ?string $action = null): AccountingPeriod
    {
        if (! $actor->hasPermission($permission) || ! $actor->hasSatisfiedMfaRequirement()) {
            throw new AuthorizationException(__('closing.not_authorised'));
        }

        return DatabaseContext::withAudit($action ?? 'period_'.$to->value, $reason, function () use ($actor, $period, $from, $to, $approval, $attributes, $gated, $reason): AccountingPeriod {
            /** @var AccountingPeriod $locked */
            $locked = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if (! in_array($locked->status, $from, true)) {
                throw RuleViolation::because('M15', 'closing.wrong_status', ['status' => $locked->status->getLabel()]);
            }

            if ($gated && ($blockers = $this->blockers($locked)) !== []) {
                throw new RuleViolation('M15', implode("\n", $blockers));
            }

            $previous = $locked->status->value;
            $locked->forceFill($attributes + ['status' => $to])->save();

            Approval::record($locked, $approval, $actor, $previous, $to->value, $reason);

            return $locked;
        });
    }
}
