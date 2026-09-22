<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Domain\Organisation\Enums\FiscalYearStatus;
use App\Domain\Organisation\Enums\PeriodStatus;
use App\Domain\Organisation\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Calendar fiscal years from the accounting start (May 2023) to the year after
 * next, with one period per month. The first year is short: it starts in May.
 * Every period is created Open; closing is a controlled act, not seed data.
 */
class FiscalCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();
        $start = $company->accounting_start;
        $lastYear = max((int) CarbonImmutable::today()->year + 1, 2027);

        for ($year = (int) $start->year; $year <= $lastYear; $year++) {
            $yearStart = $year === (int) $start->year ? $start : CarbonImmutable::create($year, 1, 1);
            $yearEnd = CarbonImmutable::create($year, 12, 31);

            $fiscalYear = FiscalYear::query()->firstOrCreate(
                ['company_id' => $company->id, 'year_code' => (string) $year],
                ['starts_on' => $yearStart, 'ends_on' => $yearEnd, 'status' => FiscalYearStatus::Open],
            );

            for ($month = (int) $yearStart->month; $month <= 12; $month++) {
                $periodStart = CarbonImmutable::create($year, $month, 1);

                AccountingPeriod::query()->firstOrCreate(
                    ['company_id' => $company->id, 'period_code' => $periodStart->format('Y-m')],
                    [
                        'fiscal_year_id' => $fiscalYear->id,
                        'period_no' => $month,
                        'starts_on' => $periodStart,
                        'ends_on' => $periodStart->endOfMonth()->startOfDay(),
                        'status' => PeriodStatus::Open,
                    ],
                );
            }
        }
    }
}
