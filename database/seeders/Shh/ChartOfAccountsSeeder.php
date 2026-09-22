<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\AccountDeriver;
use App\Domain\MasterData\ChangeRequest;
use App\Domain\MasterData\ChangeRequestService;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The approved chart: the 15 groups of Document A Appendix A, then the 145 accounts
 * of Document C tab 04, transcribed without alteration.
 *
 * Two names are then corrected the way any name is corrected -- through a change
 * request, so the audit trail shows the source name, the new name and the decision
 * that authorised it:
 *   CONF-08  211090 "Other Payables – Hassan Yaqoub" becomes "Other Payables";
 *            Hassan Yaqoub becomes a counterparty (RE-03).
 *   CONF-09  610019 "Legle" is corrected to "Legal".
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** @var array<int, array{name: string, name_ar: string, reason: string}> */
    private const CORRECTIONS = [
        '211090' => [
            'name' => 'Other Payables',
            'name_ar' => 'ذمم دائنة أخرى',
            'reason' => 'CONF-08 / RE-03: a person\'s name inside an account name prevents reuse; the account becomes generic and Hassan Yaqoub is carried as the counterparty on the line.',
        ],
        '610019' => [
            'name' => 'Approved Administrative & Legal Expenses',
            'name_ar' => 'مصروفات إدارية قانونية',
            'reason' => 'CONF-09: spelling corrected from "Legle" at migration; code, classification and balances unchanged.',
        ],
    ];

    public function run(AccountDeriver $deriver, ChangeRequestService $changes): void
    {
        $company = Company::current();

        foreach (SpecCsv::rows('account_groups', ['Group Code', 'Name (EN)', 'Name (AR)', 'Account Type']) as $row) {
            $type = AccountType::fromSpec($row['Account Type']);

            Account::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $row['Group Code']],
                [
                    'name' => $row['Name (EN)'],
                    'name_ar' => $row['Name (AR)'],
                    'account_type' => $type,
                    'account_level' => 1,
                    'is_group' => true,
                    'is_posting' => false,
                    'is_active' => true,
                    'normal_balance' => $deriver->normalBalance($type),
                    'reporting_group' => $row['Name (EN)'],
                ],
            );
        }

        $rows = SpecCsv::rows('04_COA', [
            'Account Code', 'Account Name (EN)', 'Account Name (AR)', 'Account Type', 'Parent Account',
            'Posting / Non-Posting', 'Active / Inactive', 'FS Line', 'Reporting Group',
            'Control Account (subledger)', 'Counterparty Required', 'Advance Holder Required',
        ]);

        // Two passes: 112101 names 112100 as its parent, which is itself in the chart.
        foreach ($rows as $row) {
            $code = $row['Account Code'];
            $type = AccountType::fromSpec($row['Account Type']);

            Account::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $row['Account Name (EN)'],
                    'name_ar' => $row['Account Name (AR)'],
                    'account_type' => $type,
                    'account_level' => $deriver->level($code, $row['Parent Account']),
                    'is_group' => false,
                    'is_posting' => $row['Posting / Non-Posting'] === 'Posting',
                    'is_active' => $row['Active / Inactive'] === 'Active',
                    'normal_balance' => $deriver->normalBalance($type),
                    'fs_line_code' => $row['FS Line'] ?: null,
                    'reporting_group' => $row['Reporting Group'] ?: null,
                    'is_cash_account' => $deriver->isCashAccount($code),
                    'is_advance_account' => $row['Advance Holder Required'] === 'Yes',
                    'is_control_account' => $row['Control Account (subledger)'] !== '',
                    'control_subledger' => $row['Control Account (subledger)'] ?: null,
                    'subledger' => $deriver->subledger($row['Control Account (subledger)']),
                    'requires_counterparty' => $row['Counterparty Required'] === 'Yes',
                    'requires_advance_holder' => $row['Advance Holder Required'] === 'Yes',
                    'requires_contract' => $deriver->requiresContract($code),
                ],
            );
        }

        $ids = Account::query()->where('company_id', $company->id)->pluck('id', 'code');

        foreach ($rows as $row) {
            Account::query()
                ->where('company_id', $company->id)
                ->where('code', $row['Account Code'])
                ->whereNull('parent_id')
                ->update(['parent_id' => $ids[$row['Parent Account']] ?? null]);
        }

        $this->applyCorrections($company->id, $changes);
    }

    private function applyCorrections(int $companyId, ChangeRequestService $changes): void
    {
        foreach (self::CORRECTIONS as $code => $correction) {
            $account = Account::query()->where('company_id', $companyId)->where('code', (string) $code)->firstOrFail();

            $alreadyApplied = ChangeRequest::query()
                ->where('object_type', 'account')
                ->where('object_id', $account->id)
                ->where('approval_ref', 'Document C 02_Conflicts_Decisions')
                ->exists();

            if ($alreadyApplied) {
                continue;
            }

            $request = $changes->propose(
                null,
                'account',
                $account,
                ['name' => $correction['name'], 'name_ar' => $correction['name_ar']],
                $correction['reason'],
                'Document C 02_Conflicts_Decisions',
            );

            $changes->approve(null, $request, 'Decided in the approved specification.');
        }
    }
}
