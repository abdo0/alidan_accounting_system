<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Organisation\Enums\ParameterCode;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The only reader of the parameters table.
 *
 * Values resolve by effective date, so a report re-run for a past period applies
 * the value that was in force then (Document B §2.4, VR-43). A project-scoped row
 * beats the company-wide one. A NULL value is returned as NULL -- never defaulted,
 * never inferred (VR-39: the commercial operation date).
 */
final class ParameterResolver
{
    public function value(ParameterCode $code, ?DateTimeInterface $asAt = null, ?int $projectId = null): ?string
    {
        $day = ($asAt ?? CarbonImmutable::today())->format('Y-m-d');

        $row = Parameter::query()
            ->where('company_id', Company::current()->id)
            ->where('param_code', $code->value)
            ->whereDate('effective_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $day))
            ->where(fn ($q) => $q->whereNull('project_id')->when(
                $projectId !== null,
                fn ($q) => $q->orWhere('project_id', $projectId),
            ))
            ->orderByRaw('project_id IS NULL')
            ->first();

        return $row?->value === '' ? null : $row?->value;
    }

    /** A decimal as a string, so no rate is ever carried as a binary float. */
    public function decimal(ParameterCode $code, ?DateTimeInterface $asAt = null, ?int $projectId = null): ?string
    {
        $value = $this->value($code, $asAt, $projectId);

        return $value === null ? null : (string) $value;
    }

    public function integer(ParameterCode $code, ?DateTimeInterface $asAt = null): ?int
    {
        $value = $this->value($code, $asAt);

        return $value === null ? null : (int) $value;
    }

    public function date(ParameterCode $code, ?DateTimeInterface $asAt = null): ?CarbonImmutable
    {
        $value = $this->value($code, $asAt);

        return $value === null ? null : CarbonImmutable::parse($value)->startOfDay();
    }

    /** NULL until an authorised user sets it. Nothing may assume a default. */
    public function commercialOperationDate(): ?CarbonImmutable
    {
        return $this->date(ParameterCode::CommercialOperationDate);
    }
}
