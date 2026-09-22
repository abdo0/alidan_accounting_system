<?php

declare(strict_types=1);

namespace App\Domain\Controls\Exceptions;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Controls\ExceptionComment;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Open -> Under Review -> Resolved (Document B §4.7). Resolution needs a text and a
 * resolver (VR-59); an Accountant may propose one; the Internal Auditor may
 * comment. Nothing here deletes.
 */
final class ExceptionLifecycle
{
    public function take(User $actor, ControlException $exception): ControlException
    {
        $this->require($actor, 'exceptions.propose');
        $this->requireOpen($exception);

        return DatabaseContext::withAudit('exception_take', null, function () use ($actor, $exception): ControlException {
            $exception->forceFill(['status' => ExceptionStatus::UnderReview, 'owner_id' => $actor->id])->save();

            return $exception;
        });
    }

    public function propose(User $actor, ControlException $exception, string $proposal): ControlException
    {
        $this->require($actor, 'exceptions.propose');
        $this->requireOpen($exception);

        $exception->forceFill(['proposed_resolution' => $proposal, 'status' => ExceptionStatus::UnderReview])->save();

        return $exception;
    }

    public function resolve(?User $actor, ControlException $exception, string $resolution, ?int $systemResolverId = null): ControlException
    {
        if ($actor !== null) {
            $this->require($actor, 'exceptions.resolve');
        }

        $this->requireOpen($exception);

        if (trim($resolution) === '') {
            throw RuleViolation::because('VR-59', 'validation_rules.VR-59');
        }

        $resolver = $actor->id ?? $systemResolverId ?? throw RuleViolation::because('VR-59', 'validation_rules.VR-59');

        return DatabaseContext::withAudit('exception_resolve', $resolution, function () use ($exception, $resolution, $resolver): ControlException {
            $exception->forceFill([
                'status' => ExceptionStatus::Resolved,
                'resolution' => $resolution,
                'resolved_by' => $resolver,
                'resolved_at' => now(),
            ])->save();

            return $exception;
        });
    }

    public function comment(User $actor, ControlException $exception, string $comment): ExceptionComment
    {
        $this->require($actor, 'exceptions.comment');

        return ExceptionComment::query()->create([
            'exception_id' => $exception->id,
            'user_id' => $actor->id,
            'comment' => $comment,
        ]);
    }

    private function require(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException(__('controls.exception.not_authorised'));
        }
    }

    private function requireOpen(ControlException $exception): void
    {
        if (! $exception->isOpen()) {
            throw RuleViolation::because('VR-59', 'controls.exception.already_resolved');
        }
    }
}
