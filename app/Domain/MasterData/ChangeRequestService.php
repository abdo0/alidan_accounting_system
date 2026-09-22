<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\ChangeRequestAction;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Domain\Organisation\Company;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker for the chart of accounts and master data (VR-60, RE-11).
 *
 * A change is proposed with a reason, decided by a different user holding the
 * approving permission, and applied under an audit context -- so the audit trail
 * carries the previous and new values, the reason and the approval reference
 * (UAT-050). Account codes are not in the changeable set: a code never changes once
 * transactions exist (Document B §4.1), and the chart never renumbers.
 */
final class ChangeRequestService
{
    /**
     * The objects under change control, the permission that proposes, the one that
     * approves, and the attributes a change may touch.
     *
     * @var array<string, array{class: class-string<Model>, propose: string, approve: string, fields: list<string>}>
     */
    private const OBJECTS = [
        'account' => [
            'class' => Account::class,
            'propose' => 'coa.propose',
            'approve' => 'coa.approve',
            'fields' => ['name', 'name_ar', 'is_active', 'requires_counterparty', 'requires_advance_holder', 'requires_contract', 'purpose'],
        ],
        'counterparty' => [
            'class' => Counterparty::class,
            'propose' => 'masterdata.request',
            'approve' => 'masterdata.modify',
            'fields' => ['name', 'name_ar', 'role_description', 'is_active', 'notes'],
        ],
        'project' => [
            'class' => Project::class,
            'propose' => 'masterdata.request',
            'approve' => 'masterdata.modify',
            'fields' => ['name', 'name_ar', 'is_active'],
        ],
        'cost_center' => [
            'class' => CostCenter::class,
            'propose' => 'masterdata.request',
            'approve' => 'masterdata.modify',
            'fields' => ['name', 'name_ar', 'is_active'],
        ],
    ];

    /** @param  array<string, mixed>  $changes */
    public function propose(?User $actor, string $objectType, Model $object, array $changes, string $reason, ?string $approvalRef = null): ChangeRequest
    {
        $config = $this->config($objectType);

        if ($actor !== null && ! $actor->hasPermission($config['propose']) && ! $actor->hasPermission($config['approve'])) {
            throw new AuthorizationException(__('rules.change_requests.not_authorised'));
        }

        if (trim($reason) === '') {
            throw RuleViolation::because('VR-60', 'rules.change_requests.reason_required');
        }

        $unknown = array_diff(array_keys($changes), $config['fields']);
        if ($unknown !== []) {
            throw RuleViolation::because('VR-60', 'rules.change_requests.field_not_changeable', ['fields' => implode(', ', $unknown)]);
        }

        if ($objectType === 'account' && ($changes['is_active'] ?? true) === false) {
            $this->assertAccountHasNilBalance($object);
        }

        return ChangeRequest::create([
            'company_id' => Company::current()->id,
            'object_type' => $objectType,
            'object_id' => $object->getKey(),
            'action' => ($changes['is_active'] ?? null) === false ? ChangeRequestAction::Deactivate : ChangeRequestAction::Update,
            'payload' => $changes,
            'previous' => array_intersect_key($object->only(array_keys($changes)), $changes),
            'reason' => $reason,
            'approval_ref' => $approvalRef,
            'status' => ChangeRequestStatus::Proposed,
            'proposed_by' => $actor?->id,
            'proposed_at' => now(),
        ]);
    }

    public function approve(?User $actor, ChangeRequest $request, ?string $note = null, ?string $approvalRef = null): ChangeRequest
    {
        $this->assertDecidable($actor, $request);

        return DatabaseContext::withAudit('change_request_apply', $request->reason, function () use ($actor, $request, $note, $approvalRef): ChangeRequest {
            $config = $this->config($request->object_type);
            /** @var Model $object */
            $object = $config['class']::query()->lockForUpdate()->findOrFail($request->object_id);

            if ($request->object_type === 'account' && ($request->payload['is_active'] ?? true) === false) {
                $this->assertAccountHasNilBalance($object);
            }

            $request->forceFill([
                'status' => ChangeRequestStatus::Approved,
                'decided_by' => $actor?->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'approval_ref' => $approvalRef ?? $request->approval_ref,
            ])->save();

            $object->forceFill($request->payload)->save();

            $request->forceFill(['status' => ChangeRequestStatus::Applied, 'applied_at' => now()])->save();

            return $request;
        });
    }

    public function reject(User $actor, ChangeRequest $request, string $note): ChangeRequest
    {
        $this->assertDecidable($actor, $request);

        if (trim($note) === '') {
            throw RuleViolation::because('VR-60', 'rules.change_requests.reason_required');
        }

        $request->forceFill([
            'status' => ChangeRequestStatus::Rejected,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $request;
    }

    private function assertDecidable(?User $actor, ChangeRequest $request): void
    {
        if ($request->status !== ChangeRequestStatus::Proposed) {
            throw RuleViolation::because('VR-60', 'rules.change_requests.not_open');
        }

        // A null actor is the system itself applying a documented decision (the
        // CONF-08 / CONF-09 corrections at seed time).
        if ($actor === null) {
            return;
        }

        if (! $actor->hasPermission($this->config($request->object_type)['approve'])) {
            throw new AuthorizationException(__('rules.change_requests.not_authorised'));
        }

        if ($request->proposed_by !== null && $request->proposed_by === $actor->id) {
            throw RuleViolation::because('VR-60', 'rules.change_requests.maker_checker');
        }
    }

    /** An account may be deactivated only when its balance is nil (Document B §4.1). */
    private function assertAccountHasNilBalance(Model $account): void
    {
        if (! DB::getSchemaBuilder()->hasTable('journal_lines')) {
            return;
        }

        $net = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jl.account_id', $account->getKey())
            ->whereIn('jh.status', ['posted', 'reversed'])
            ->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS net')
            ->value('net');

        if ((string) $net !== '0' && (float) $net !== 0.0) {
            throw RuleViolation::because('VR-60', 'rules.change_requests.balance_not_nil');
        }
    }

    /** @return array{class: class-string<Model>, propose: string, approve: string, fields: list<string>} */
    private function config(string $objectType): array
    {
        return self::OBJECTS[$objectType] ?? throw new \InvalidArgumentException("{$objectType} is not under change control.");
    }
}
