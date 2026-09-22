<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Workflow;

use App\Domain\Ledger\Enums\ApprovalAction;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\ValidationChain;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Shared\Approval;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The journal state machine of Document B §4.2:
 *
 *   Draft --submit--> Submitted --review--> Reviewed --approve--> Approved --post--> Posted
 *     ^                  |                     |                     |                 |
 *     +---- reject ------+------- reject ------+------ reject -------+             reverse
 *
 * Every transition checks the actor's permission server-side, runs its validation
 * checkpoint, and writes an approvals row and an audit row. VR-16 (maker != checker)
 * is checked at Review and at Approve. A transaction type whose approval path has
 * no reviewer (Document C tab 12) goes from Submitted straight to approval.
 */
final class JournalWorkflow
{
    public function __construct(
        private readonly ValidationChain $chain,
        private readonly PostingService $posting,
    ) {}

    /** @param  list<string>  $acknowledgedWarnings  rule codes the user has acknowledged */
    public function submit(User $actor, JournalHeader $journal, array $acknowledgedWarnings = []): JournalHeader
    {
        $this->requireStatus($journal, JournalStatus::Draft);

        if (! $actor->hasPermission('journal.submit') || ! $actor->hasPermissionOver('journal.view', $journal->created_by)) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }

        return DatabaseContext::withAudit('journal_submit', null, function () use ($actor, $journal, $acknowledgedWarnings): JournalHeader {
            $context = new PostingContext($journal, $actor, Checkpoint::Submit);
            $result = $this->chain->runContext($context);
            $findings = [...$result->blocking(), ...$result->unacknowledgedWarnings($acknowledgedWarnings)];

            if ($findings !== []) {
                throw new JournalRejected($findings);
            }

            return $this->transition($journal, $actor, ApprovalAction::Submit, JournalStatus::Submitted, [
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'posting_rule_id' => $context->postingRule->id ?? $journal->posting_rule_id,
                'acknowledged_warnings' => array_values(array_intersect($acknowledgedWarnings, $result->ruleCodes())),
            ]);
        });
    }

    public function review(User $actor, JournalHeader $journal, ?string $comment = null): JournalHeader
    {
        $this->requireStatus($journal, JournalStatus::Submitted);
        $this->requirePermission($actor, 'journal.review');

        return DatabaseContext::withAudit('journal_review', $comment, function () use ($actor, $journal, $comment): JournalHeader {
            $this->runCheckpoint($journal, $actor, Checkpoint::Review);

            return $this->transition($journal, $actor, ApprovalAction::Review, JournalStatus::Reviewed, [
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ], $comment);
        });
    }

    public function approve(User $actor, JournalHeader $journal, ?string $approvalRef = null, ?string $comment = null): JournalHeader
    {
        $journal->loadMissing('transactionType');

        $ready = $journal->status === JournalStatus::Reviewed
            || ($journal->status === JournalStatus::Submitted && ! $journal->transactionType->requires_review);

        if (! $ready) {
            $this->requireStatus($journal, JournalStatus::Reviewed);
        }

        $this->requirePermission($actor, 'journal.approve');

        return DatabaseContext::withAudit('journal_approve', $comment, function () use ($actor, $journal, $approvalRef, $comment): JournalHeader {
            if ($approvalRef !== null && trim($approvalRef) !== '') {
                $journal->forceFill(['approval_ref' => trim($approvalRef)]);
            }

            $this->runCheckpoint($journal, $actor, Checkpoint::Approve);

            return $this->transition($journal, $actor, ApprovalAction::Approve, JournalStatus::Approved, [
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ], $comment, $journal->approval_ref);
        });
    }

    /** Rejection returns the entry to Draft with a mandatory comment. */
    public function reject(User $actor, JournalHeader $journal, string $comment): JournalHeader
    {
        if (! $journal->status->isRejectable()) {
            throw $this->statusViolation($journal);
        }

        if (! $actor->hasPermission('journal.review') && ! $actor->hasPermission('journal.approve')) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }

        if (trim($comment) === '') {
            throw new JournalRejected([new Violation('M03', Severity::Blocking, __('rules.journal.rejection_comment'))]);
        }

        return DatabaseContext::withAudit('journal_reject', $comment, fn (): JournalHeader => $this->transition($journal, $actor, ApprovalAction::Reject, JournalStatus::Draft, [
            'rejection_comment' => $comment,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ], $comment));
    }

    public function post(User $actor, JournalHeader $journal): JournalHeader
    {
        return $this->posting->post($actor, $journal);
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(JournalHeader $journal, User $actor, ApprovalAction $action, JournalStatus $to, array $attributes, ?string $comment = null, ?string $approvalRef = null): JournalHeader
    {
        $from = $journal->status->value;

        $journal->forceFill($attributes + ['status' => $to])->save();

        Approval::record($journal, $action, $actor, $from, $to->value, $comment, $approvalRef);

        return $journal;
    }

    private function runCheckpoint(JournalHeader $journal, User $actor, Checkpoint $checkpoint): void
    {
        $result = $this->chain->run($journal, $actor, $checkpoint);

        if ($result->hasBlocking()) {
            throw new JournalRejected($result->blocking());
        }
    }

    private function requireStatus(JournalHeader $journal, JournalStatus $expected): void
    {
        if ($journal->status !== $expected) {
            throw $this->statusViolation($journal);
        }
    }

    private function requirePermission(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission) || ! $actor->hasSatisfiedMfaRequirement()) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }
    }

    private function statusViolation(JournalHeader $journal): JournalRejected
    {
        $rule = $journal->status->isInLedger() ? 'VR-18' : 'M03';

        return new JournalRejected([new Violation($rule, Severity::Blocking, __('rules.journal.wrong_status', ['status' => $journal->status->getLabel()]))]);
    }
}
