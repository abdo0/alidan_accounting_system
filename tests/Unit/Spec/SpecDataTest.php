<?php

declare(strict_types=1);

namespace Tests\Unit\Spec;

use App\Support\Spec\SpecCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The extracted Document C must hold what Document C says it holds. A re-issue that
 * changes a count is a change to review, not something to seed silently.
 */
class SpecDataTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function tabs(): array
    {
        return [
            'chart of accounts' => ['04_COA', 145],
            'projects' => ['05_Projects', 4],
            'cost centres' => ['06_Cost_Centers', 19],
            'responsibility centres' => ['07_Resp_Centers', 3],
            'master data' => ['08_Master_Data', 17],
            'cash accounts' => ['09_Cash_Accounts', 8],
            'transaction types' => ['12_Transaction_Types', 39],
            'posting rules' => ['13_Posting_Rules', 35],
            'account mapping' => ['15_Account_FS_Map', 143],
            'report filters' => ['17_Report_Filters', 30],
            'validation rules' => ['18_Validation_Rules', 60],
            'roles' => ['19_User_Roles', 8],
            'migration controls' => ['23_Migration_Controls', 30],
            'exceptions' => ['24_Exceptions', 22],
            'UAT cases' => ['25_UAT_Cases', 52],
            'parameters' => ['27_Parameters', 15],
            'account groups' => ['account_groups', 15],
            'closing tasks' => ['closing_tasks', 25],
            'chain steps' => ['chain_step_pairs', 12],
        ];
    }

    #[Test]
    #[DataProvider('tabs')]
    public function each_tab_holds_the_documented_number_of_rows(string $tab, int $expected): void
    {
        $this->assertCount($expected, SpecCsv::rows($tab));
    }

    #[Test]
    public function the_chart_counts_match_the_approved_structure(): void
    {
        $chart = collect(SpecCsv::rows('04_COA', ['Account Code', 'Posting / Non-Posting', 'Active / Inactive']));

        $this->assertSame(141, $chart->where('Posting / Non-Posting', 'Posting')->where('Active / Inactive', 'Active')->count());
        $this->assertSame(['112100', '115030'], $chart->where('Posting / Non-Posting', 'Non-Posting')->pluck('Account Code')->values()->all());
        $this->assertSame(['112031', '112036'], $chart->where('Active / Inactive', 'Inactive')->pluck('Account Code')->values()->all());
    }

    #[Test]
    public function every_parent_in_the_chart_is_a_group_or_an_account(): void
    {
        $codes = collect(SpecCsv::rows('04_COA'))->pluck('Account Code')
            ->merge(collect(SpecCsv::rows('account_groups'))->pluck('Group Code'));

        foreach (SpecCsv::rows('04_COA') as $row) {
            $this->assertContains($row['Parent Account'], $codes, "Parent of {$row['Account Code']} is unknown.");
        }
    }

    #[Test]
    public function a_missing_column_fails_loudly(): void
    {
        $this->expectExceptionMessage('lacks column(s): Nonexistent');

        SpecCsv::rows('05_Projects', ['Code', 'Nonexistent']);
    }
}
