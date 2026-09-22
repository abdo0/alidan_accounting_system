<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Advances\Enums\EvidenceStatus;
use App\Domain\Advances\Enums\SettlementClassification;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Exceptions\ExceptionRaiser;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The settlement audit (RPT-15): classifying settlements as the working paper does
 * (A valid PRJ-01, B valid PRJ-03, C personal receivable, D pending evidence), and
 * recording a claim still awaiting evidence. A pending claim is not posted: it
 * leaves the amount in the advance and holds an open exception until the evidence
 * arrives and the settlement is posted in the ledger.
 */
final class AdvanceClaimService
{
    public function __construct(private readonly ExceptionRaiser $exceptions) {}

    public function recordPendingClaim(User $actor, Advance $advance, int $amount, string $notes): AdvanceSettlement
    {
        $this->authorise($actor);

        if ($amount <= 0) {
            throw RuleViolation::because('M04', 'rules.advances.claim_amount');
        }

        $claim = AdvanceSettlement::query()->create([
            'advance_id' => $advance->id,
            'claimed_amount' => $amount,
            'settlement_type' => SettlementType::Other,
            'classification' => SettlementClassification::PendingEvidence,
            'evidence_status' => EvidenceStatus::Pending,
            'reviewed_by' => $actor->id,
            'notes' => $notes,
        ]);

        $this->exceptions->raise(
            ExceptionCategory::PendingEvidence,
            __('controls.exception.pending_evidence', ['ref' => $advance->advance_ref]),
            ['counterparty_id' => $advance->holder_id, 'account_id' => $advance->account_id, 'amount' => $amount],
            'claim:'.$claim->id,
        );

        return $claim;
    }

    public function classify(User $actor, AdvanceSettlement $settlement, SettlementClassification $classification, ?EvidenceStatus $evidence, ?string $notes = null): AdvanceSettlement
    {
        $this->authorise($actor);

        $settlement->forceFill([
            'classification' => $classification,
            'evidence_status' => $evidence,
            'reviewed_by' => $actor->id,
            'notes' => $notes ?? $settlement->getAttribute('notes'),
        ])->save();

        return $settlement;
    }

    private function authorise(User $actor): void
    {
        if (! $actor->hasPermission('journal.review') && ! $actor->hasPermission('exceptions.propose')) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }
    }
}
