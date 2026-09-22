<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\Enums\ParameterType;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Changes a parameter by closing the value in force and opening a new one.
 *
 * Only the Finance Manager may change a parameter (Document C tab 19), always with
 * a reason and an approval reference. The commercial operation date and the
 * government share rate are high-risk: the audit row is marked as such
 * (Document B §4.9).
 */
final class ParameterService
{
    public function change(
        User $actor,
        ParameterCode $code,
        ?string $value,
        CarbonImmutable $effectiveFrom,
        string $approvalRef,
        string $reason,
        ?int $projectId = null,
    ): Parameter {
        if (! $actor->hasPermission('parameters.manage')) {
            throw new AuthorizationException(__('rules.parameters.not_authorised'));
        }

        if (trim($reason) === '' || trim($approvalRef) === '') {
            throw RuleViolation::because('M00', 'rules.parameters.reason_required');
        }

        $value = $value === null || trim($value) === '' ? null : trim($value);
        $this->assertValueMatchesType($code, $value);

        $company = Company::current();
        $action = $code->isHighRisk() ? 'high_risk_param_change' : 'param_change';

        return DatabaseContext::withAudit($action, $reason, function () use ($actor, $code, $value, $effectiveFrom, $approvalRef, $reason, $projectId, $company): Parameter {
            $current = Parameter::query()
                ->where('company_id', $company->id)
                ->where('param_code', $code->value)
                ->where('project_id', $projectId)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                if ($current->effective_from->greaterThanOrEqualTo($effectiveFrom)) {
                    throw RuleViolation::because('M00', 'rules.parameters.not_after_current', [
                        'from' => $current->effective_from->toDateString(),
                    ]);
                }

                $current->forceFill(['effective_to' => $effectiveFrom])->save();
            }

            return Parameter::create([
                'company_id' => $company->id,
                'param_code' => $code->value,
                'spec_ref' => $code->specRef(),
                'name' => $current->name ?? $code->getLabel(),
                'name_ar' => $current?->name_ar,
                'data_type' => $code->dataType(),
                'value' => $value,
                'project_id' => $projectId,
                'effective_from' => $effectiveFrom,
                'approval_ref' => $approvalRef,
                'reason' => $reason,
                'source' => 'Changed in the system',
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);
        });
    }

    private function assertValueMatchesType(ParameterCode $code, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $valid = match ($code->dataType()) {
            ParameterType::Decimal => is_numeric($value),
            ParameterType::Integer => ctype_digit(ltrim($value, '-')),
            ParameterType::Date => (bool) strtotime($value),
            ParameterType::Boolean => in_array(strtolower($value), ['true', 'false', '1', '0'], true),
            ParameterType::String => true,
        };

        if (! $valid) {
            throw RuleViolation::because('M00', 'rules.parameters.bad_value', ['type' => $code->dataType()->value]);
        }
    }
}
