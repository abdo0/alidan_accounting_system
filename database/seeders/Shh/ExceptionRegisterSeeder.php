<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Organisation\Company;
use App\Domain\Organisation\FiscalYear;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The system exceptions of the register (Document C tab 24) that stand on their own:
 * reference-data gaps, the incomplete 2026 source and the missing contract data.
 * They load Open, as the source has them.
 *
 * EXC-OPEN-01 ... 17 and EXC-SYS-03 concern specific ledger rows, so they arrive with
 * the migration of the authoritative journal, attached to the entries they describe.
 */
class ExceptionRegisterSeeder extends Seeder
{
    /** @var array<string, ExceptionCategory> */
    private const STANDALONE = [
        'EXC-SYS-01' => ExceptionCategory::ReferenceDataGap,
        'EXC-SYS-02' => ExceptionCategory::ReferenceDataGap,
        'EXC-SYS-04' => ExceptionCategory::SourceIncomplete,
        'EXC-SYS-05' => ExceptionCategory::ContractDataMissing,
    ];

    public function run(): void
    {
        $company = Company::current();

        foreach (SpecCsv::rows('24_Exceptions', ['Exception ID', 'Category', 'Subject', 'Amount / Volume', 'Status', 'Description', 'Required action']) as $row) {
            $category = self::STANDALONE[$row['Exception ID']] ?? null;

            if ($category === null) {
                continue;
            }

            // The 2026 source is explicitly incomplete: its periods may not be finally
            // closed until it is confirmed complete (Document B §4.11).
            $blocksYear = $row['Exception ID'] === 'EXC-SYS-04'
                ? FiscalYear::query()->where('company_id', $company->id)->where('year_code', '2026')->value('id')
                : null;

            ControlException::query()->firstOrCreate(
                ['source_code' => $row['Exception ID']],
                [
                    'company_id' => $company->id,
                    'exception_no' => $row['Exception ID'],
                    'category' => $category,
                    'raised_date' => $company->accounting_start,
                    'subject' => $row['Subject'],
                    'description' => $row['Description'],
                    'volume' => $row['Amount / Volume'],
                    'required_action' => $row['Required action'],
                    'status' => ExceptionStatus::Open,
                    'fiscal_year_id' => $blocksYear,
                    'blocks_final_close' => $blocksYear !== null,
                ],
            );
        }
    }
}
