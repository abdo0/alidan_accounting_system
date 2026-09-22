<?php

declare(strict_types=1);

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Enums\ReconciliationType;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Exceptions\ExceptionRaiser;
use App\Domain\MasterData\BankAccount;
use App\Domain\Shared\Documents\DocumentService;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

/**
 * Cash counts and bank reconciliations (M11, VR-58). A reconciliation is prepared
 * with the actual balance, its supporting document, the responsible accountant and
 * the date -- all four, or it is refused -- and approved by someone else. On
 * approval a non-zero variance leaves the account Unreconciled and raises an
 * exception. Nothing here writes to the ledger.
 */
final class ReconciliationService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly BookBalance $books,
        private readonly ExceptionRaiser $exceptions,
    ) {}

    public function prepare(
        User $actor,
        BankAccount $account,
        CarbonImmutable $asAt,
        int $actualBalance,
        ?UploadedFile $evidence,
        ?User $responsible,
        ReconciliationType $type = ReconciliationType::Bank,
        ?string $notes = null,
    ): Reconciliation {
        if (! $actor->hasPermission('cash.reconcile_prepare')) {
            throw new AuthorizationException(__('rules.cash.not_authorised'));
        }

        if ($evidence === null || $responsible === null) {
            throw RuleViolation::because('VR-58', 'validation_rules.VR-58');
        }

        return DatabaseContext::withAudit('cash_reconcile_prepare', $notes, function () use ($actor, $account, $asAt, $actualBalance, $evidence, $responsible, $type, $notes): Reconciliation {
            $reconciliation = Reconciliation::query()->create([
                'recon_type' => $type,
                'bank_account_id' => $account->id,
                'account_id' => $account->account_id,
                'as_at_date' => $asAt,
                'actual_balance' => $actualBalance,
                'responsible_user_id' => $responsible->id,
                'status' => ReconciliationStatus::Submitted,
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
                'notes' => $notes,
            ]);

            $document = $this->documents->attach($actor, $reconciliation, $evidence, $type === ReconciliationType::Bank ? 'bank_statement' : 'other');
            $reconciliation->forceFill(['document_id' => $document->id])->save();

            return $reconciliation;
        });
    }

    public function approve(User $actor, Reconciliation $reconciliation): Reconciliation
    {
        if (! $actor->hasPermission('cash.reconcile_approve')) {
            throw new AuthorizationException(__('rules.cash.not_authorised'));
        }

        if ($reconciliation->prepared_by === $actor->id) {
            throw RuleViolation::because('VR-16', 'validation_rules.VR-16');
        }

        if ($reconciliation->status !== ReconciliationStatus::Submitted) {
            throw RuleViolation::because('VR-58', 'rules.cash.not_submitted');
        }

        return DatabaseContext::withAudit('cash_reconcile_approve', null, function () use ($actor, $reconciliation): Reconciliation {
            $reconciliation->forceFill([
                'status' => ReconciliationStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $variance = $this->books->variance($reconciliation);

            if ($variance !== 0) {
                $this->exceptions->raise(
                    ExceptionCategory::CashVariance,
                    __('controls.exception.cash_variance', ['account' => $reconciliation->bankAccount?->code, 'date' => $reconciliation->as_at_date->toDateString()]),
                    ['account_id' => $reconciliation->account_id, 'amount' => $variance, 'owner_id' => $reconciliation->responsible_user_id],
                    'reconciliation:'.$reconciliation->id,
                );
            }

            return $reconciliation;
        });
    }

    /** Unreconciled: approved with a variance, or never reconciled. */
    public function isReconciled(Reconciliation $reconciliation): bool
    {
        return $reconciliation->status === ReconciliationStatus::Approved && $this->books->variance($reconciliation) === 0;
    }
}
