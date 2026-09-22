<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Enums\BankAccountType;
use App\Domain\MasterData\Project;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * One bank account, cash box or safe per 111xxx account (Document C tab 09, MIG-08).
 * Custodian and responsible accountant are not in the source; they stay empty
 * until assigned, and period close asks for them (RE-18).
 */
class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();
        $projects = Project::query()->where('company_id', $company->id)->pluck('id', 'code');

        foreach (SpecCsv::rows('09_Cash_Accounts', ['GL Account', 'Name (EN)', 'Name (AR)', 'Type', 'Project']) as $row) {
            $account = Account::query()->where('company_id', $company->id)->where('code', $row['GL Account'])->firstOrFail();

            BankAccount::query()->updateOrCreate(
                ['account_id' => $account->id],
                [
                    'company_id' => $company->id,
                    'code' => $row['GL Account'],
                    'name' => $row['Name (EN)'],
                    'name_ar' => $row['Name (AR)'],
                    'ba_type' => BankAccountType::from(strtolower($row['Type'])),
                    'project_id' => $projects[$row['Project']] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
