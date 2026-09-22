<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\AccountDeriver;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\CounterpartyAlias;
use App\Domain\MasterData\Enums\CounterpartyType;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\Shareholder;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * Shareholders, advance holders and contractors (Document C tab 08), the
 * responsibility centres (tab 07), and the two parties the specification names
 * elsewhere: Hassan Yaqoub (CONF-08) and the State Company for Land Transport
 * (PARAM-011).
 *
 * CN-11 Gulf Shield is the same counterparty family as CN-04 Deraa Al-Khaleej: it is
 * merged under CN-04 and kept as an alias (Document B §4.1).
 */
class CounterpartySeeder extends Seeder
{
    /** @var array<string, string> merged code => surviving code */
    private const MERGED = ['CN-11' => 'CN-04'];

    public function run(): void
    {
        $company = Company::current();
        $accounts = Account::query()->where('company_id', $company->id)->pluck('id', 'code');

        $rows = SpecCsv::rows('08_Master_Data', ['Category', 'Code', 'Name (EN)', 'Name (AR)', 'Role / Category', 'Linked Accounts', 'Note']);

        foreach ($rows as $row) {
            if (isset(self::MERGED[$row['Code']])) {
                continue;
            }

            $type = match ($row['Category']) {
                'Shareholder' => CounterpartyType::Shareholder,
                'Advance Holder' => CounterpartyType::AdvanceHolder,
                default => str_contains($row['Role / Category'], 'Supplier') && ! str_contains($row['Role / Category'], 'Contractor')
                    ? CounterpartyType::Supplier
                    : CounterpartyType::Contractor,
            };

            $counterparty = Counterparty::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['Code']],
                [
                    'name' => $row['Name (EN)'],
                    'name_ar' => $row['Name (AR)'],
                    'cp_type' => $type,
                    'role_description' => $row['Role / Category'],
                    'is_contractor' => $type === CounterpartyType::Contractor || str_contains($row['Role / Category'], 'Contractor'),
                    'is_supplier' => str_contains($row['Role / Category'], 'Supplier'),
                    'is_advance_holder' => $type === CounterpartyType::AdvanceHolder,
                    'is_shareholder' => $type === CounterpartyType::Shareholder,
                    'notes' => $row['Note'] ?: null,
                    'is_active' => true,
                ],
            );

            $linked = AccountDeriver::codesIn($row['Linked Accounts']);

            if ($type === CounterpartyType::Shareholder) {
                $this->linkShareholder($counterparty, $row['Linked Accounts'], $accounts->all());
            }

            if ($type === CounterpartyType::AdvanceHolder) {
                Account::query()->whereIn('code', $linked)->where('company_id', $company->id)
                    ->update(['holder_counterparty_id' => $counterparty->id]);
            }
        }

        foreach ($rows as $row) {
            if (! isset(self::MERGED[$row['Code']])) {
                continue;
            }

            $survivor = Counterparty::query()->where('company_id', $company->id)
                ->where('code', self::MERGED[$row['Code']])->firstOrFail();

            CounterpartyAlias::query()->firstOrCreate(
                ['counterparty_id' => $survivor->id, 'alias' => $row['Name (EN)']],
                ['legacy_code' => $row['Code'], 'source' => 'Document C 08_Master_Data — merged per Document B §4.1'],
            );
        }

        $this->seedNamedParties($company->id);
        $this->seedResponsibilityCenters($company->id);
    }

    /** @param  array<string, int>  $accounts */
    private function linkShareholder(Counterparty $counterparty, string $linked, array $accounts): void
    {
        preg_match('/Loan\s+(\d{6})/', $linked, $loan);
        preg_match('/Current\s+(\d{6})/', $linked, $current);
        preg_match('/Capital\s+(\d{6})/', $linked, $capital);

        Shareholder::query()->updateOrCreate(
            ['counterparty_id' => $counterparty->id],
            [
                'code' => $counterparty->code,
                'loan_account_id' => $accounts[$loan[1] ?? ''] ?? null,
                'current_account_id' => $accounts[$current[1] ?? ''] ?? null,
                'capital_account_id' => $accounts[$capital[1] ?? ''] ?? null,
                // SH-01 is the approved project financier (Document C tab 08).
                'is_approved_financier' => str_contains($counterparty->role_description ?? '', 'financier'),
            ],
        );
    }

    private function seedNamedParties(int $companyId): void
    {
        Counterparty::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => 'TP-01'],
            [
                'name' => 'Hassan Yaqoub',
                'name_ar' => 'حسن يعقوب',
                'cp_type' => CounterpartyType::Other,
                'role_description' => 'Non-shareholder funder recognised under 211090 (board decision on JV-1028)',
                'source_alias' => 'Other Payables – Hassan Yaqoub',
                'notes' => 'CONF-08 / RE-03',
                'is_active' => true,
            ],
        );

        $government = collect(SpecCsv::rows('27_Parameters'))->firstWhere('Parameter ID', 'PARAM-011');
        [$nameEn, $nameAr] = array_map('trim', explode('/', (string) $government['Value'], 2)) + [1 => null];

        Counterparty::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => 'GOV-01'],
            [
                'name' => $nameEn,
                'name_ar' => $nameAr,
                'cp_type' => CounterpartyType::Government,
                'role_description' => 'Concession partner; payee of the government revenue share (213001)',
                'is_government' => true,
                'notes' => 'PARAM-011',
                'is_active' => true,
            ],
        );
    }

    /**
     * RC-01 and RC-02 belong to the two custodians; each owns the advance accounts
     * tab 07 lists. The link is what lets migration derive the centre (CONF-06).
     */
    private function seedResponsibilityCenters(int $companyId): void
    {
        foreach (SpecCsv::rows('07_Resp_Centers', ['Code', 'Responsibility Center (EN)', 'Responsibility Center (AR)', 'Advance Accounts']) as $row) {
            $codes = AccountDeriver::codesIn($row['Advance Accounts']);

            $holderId = $codes === [] ? null : Account::query()
                ->where('company_id', $companyId)
                ->whereIn('code', $codes)
                ->whereNotNull('holder_counterparty_id')
                ->value('holder_counterparty_id');

            $center = ResponsibilityCenter::query()->updateOrCreate(
                ['company_id' => $companyId, 'code' => $row['Code']],
                [
                    'name' => $row['Responsibility Center (EN)'],
                    'name_ar' => $row['Responsibility Center (AR)'],
                    'linked_counterparty_id' => $holderId,
                    'is_active' => true,
                ],
            );

            if ($codes !== []) {
                Account::query()->where('company_id', $companyId)->whereIn('code', $codes)
                    ->update(['responsibility_center_id' => $center->id]);
            }
        }
    }
}
