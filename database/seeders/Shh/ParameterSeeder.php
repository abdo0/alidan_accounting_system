<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Organisation\Company;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\Enums\ParameterType;
use App\Domain\Organisation\Parameter;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * PARAM-001 ... PARAM-015 (Document C tab 27), effective from the accounting start.
 *
 * A value the source marks "To be defined" loads as NULL. The commercial operation
 * date is NULL by specification and the system must never infer one (VR-39). The
 * government share rate lives here and nowhere else (acceptance criterion 12).
 *
 * The keys below the PARAM series are settings the specification relies on without
 * giving a value; they load NULL, except the duplicate-detection window, which
 * Document B §2.5 states as ±7 days.
 */
class ParameterSeeder extends Seeder
{
    /** @var array<string, array{?string, string}> code => [value, source] */
    private const UNNUMBERED = [
        'contract_threshold_vr29' => [null, 'VR-29 — threshold not yet approved'],
        'migration_cutoff_date' => [null, 'Document B §9.2 — cut-off not yet set'],
        'go_live_date' => [null, 'VR-54 — go-live not yet set'],
        'duplicate_near_date_days' => ['7', 'Document B §2.5'],
    ];

    public function run(): void
    {
        $company = Company::current();
        $effectiveFrom = $company->accounting_start;

        foreach (SpecCsv::rows('27_Parameters', ['Parameter ID', 'Parameter (EN)', 'المعامل (عربي)', 'Value', 'Source']) as $row) {
            $code = ParameterCode::fromSpecRef($row['Parameter ID']);

            $this->seed($company->id, $code, $this->normalise($code, $row['Value']), $row['Parameter (EN)'], $row['المعامل (عربي)'], $row['Source'], $effectiveFrom);
        }

        foreach (self::UNNUMBERED as $code => [$value, $source]) {
            $case = ParameterCode::from($code);

            $this->seed($company->id, $case, $value, __('enums.parameter_code.'.$code, [], 'en'), __('enums.parameter_code.'.$code, [], 'ar'), $source, $effectiveFrom);
        }
    }

    private function seed(int $companyId, ParameterCode $code, ?string $value, string $name, ?string $nameAr, string $source, \DateTimeInterface $from): void
    {
        $exists = Parameter::query()
            ->where('company_id', $companyId)
            ->where('param_code', $code->value)
            ->exists();

        // History is never rewritten: once a parameter has a row, a re-seed leaves it.
        if ($exists) {
            return;
        }

        Parameter::query()->create([
            'company_id' => $companyId,
            'param_code' => $code->value,
            'spec_ref' => $code->specRef(),
            'name' => $name,
            'name_ar' => $nameAr,
            'data_type' => $code->dataType(),
            'value' => $value,
            'effective_from' => $from,
            'source' => $source,
            'changed_at' => now(),
        ]);
    }

    private function normalise(ParameterCode $code, string $value): ?string
    {
        if (str_contains($value, 'To be defined') || str_contains($value, 'يُحدد')) {
            return null;
        }

        return match ($code->dataType()) {
            ParameterType::Date => AccountingStart::fromSpec($value)->toDateString(),
            default => $value,
        };
    }
}
