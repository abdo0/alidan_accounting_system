<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Contract;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Enums\ContractStatus;
use App\Domain\MasterData\Enums\ContractType;
use App\Domain\Organisation\Company;
use Illuminate\Database\Seeder;

/**
 * The government concession contract (MIG-09). Every field the source marks "To be
 * defined" loads as NULL and is named in pending_fields (EXC-SYS-05). The initial
 * term is not copied onto the contract: it is PARAM-008, read from the parameters.
 */
class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();
        $government = Counterparty::query()->where('company_id', $company->id)->where('code', 'GOV-01')->firstOrFail();

        Contract::query()->firstOrCreate(
            ['company_id' => $company->id, 'contract_no' => 'CONC-01'],
            [
                'title' => 'Government concession contract — joint operation of the trade exchange yards',
                'title_ar' => 'عقد الامتياز الحكومي — التشغيل المشترك لساحات التبادل التجاري',
                'contract_type' => ContractType::Concession,
                'counterparty_id' => $government->id,
                'status' => ContractStatus::Pending,
                'pending_fields' => ['contract_value', 'signed_on', 'starts_on', 'ends_on', 'term_years', 'retention_pct', 'advance_recovery_pct'],
                'notes' => 'EXC-SYS-05: contract data missing in the source.',
            ],
        );
    }
}
