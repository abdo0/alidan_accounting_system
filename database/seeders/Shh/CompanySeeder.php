<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/** SHH-01, from PARAM-001 ... PARAM-005 and PARAM-010 (Document C tab 27). */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $params = collect(SpecCsv::rows('27_Parameters', ['Parameter ID', 'Value']))
            ->pluck('Value', 'Parameter ID');

        [$nameEn, $nameAr] = array_map('trim', explode('/', (string) $params['PARAM-001'], 2)) + [1 => null];

        Company::query()->updateOrCreate(
            ['code' => (string) $params['PARAM-002']],
            [
                'name' => $nameEn,
                'name_ar' => $nameAr,
                'operating_model' => $params['PARAM-003'],
                'currency' => (string) $params['PARAM-004'],
                'decimals_allowed' => false,
                'accounting_start' => AccountingStart::fromSpec((string) $params['PARAM-010']),
                'is_active' => true,
            ],
        );
    }
}
