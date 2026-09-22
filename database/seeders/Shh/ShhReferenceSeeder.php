<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use Illuminate\Database\Seeder;

/**
 * All SHH-01 reference data, in dependency order. Every seeder reads the committed
 * Document C extract under database/data/shh and is idempotent, keyed by the spec's
 * own codes, so it is safe to run again after a re-issue.
 *
 * No historical journal row is seeded here: the ledger arrives only through the
 * migration importer, from the authoritative workbook.
 */
class ShhReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            CompanySeeder::class,
            FiscalCalendarSeeder::class,
            DimensionSeeder::class,
            ChartOfAccountsSeeder::class,
            CounterpartySeeder::class,
            ParameterSeeder::class,
            BankAccountSeeder::class,
            FundingSeeder::class,
            ValueListSeeder::class,
            ContractSeeder::class,
            TransactionTypeSeeder::class,
            PostingRuleSeeder::class,
            ExceptionRegisterSeeder::class,
            ReportingSeeder::class,
            ClosingChecklistSeeder::class,
        ]);
    }
}
