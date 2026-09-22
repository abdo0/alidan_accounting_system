<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\Enums\NormalBalance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** M01, MIG-01. The approved chart, transcribed and derived as Document B §4.1 says. */
class ChartOfAccountsTest extends TestCase
{
    #[Test]
    #[Group('MIG-01')]
    public function the_chart_holds_the_approved_structure(): void
    {
        $accounts = Account::query()->where('is_group', false);

        $this->assertSame(145, (clone $accounts)->count());
        $this->assertSame(141, (clone $accounts)->where('is_posting', true)->where('is_active', true)->count());
        $this->assertSame(['112100', '115030'], (clone $accounts)->where('is_posting', false)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['112031', '112036'], (clone $accounts)->where('is_active', false)->orderBy('code')->pluck('code')->all());
        $this->assertSame(15, Account::query()->where('is_group', true)->count());
    }

    #[Test]
    public function levels_follow_the_parent_structure(): void
    {
        $levels = Account::query()->pluck('account_level', 'code');

        $this->assertSame(1, $levels['111000']);
        $this->assertSame(2, $levels['111002']);
        $this->assertSame(2, $levels['115030']);
        $this->assertSame(3, $levels['115032']);
        $this->assertSame(3, $levels['112101']);
        $this->assertSame('115030', Account::query()->where('code', '115032')->firstOrFail()->parent()->value('code'));
    }

    #[Test]
    public function normal_balance_follows_the_account_type(): void
    {
        $balances = Account::query()->get()->mapWithKeys(fn (Account $a): array => [$a->code => $a->normal_balance]);

        $this->assertSame(NormalBalance::Debit, $balances['111002']);
        $this->assertSame(NormalBalance::Credit, $balances['221001']);
        $this->assertSame(NormalBalance::Credit, $balances['310010']);
        $this->assertSame(NormalBalance::Credit, $balances['410001']);
        $this->assertSame(NormalBalance::Debit, $balances['610019']);
    }

    #[Test]
    public function cash_accounts_are_exactly_111001_to_111040(): void
    {
        $this->assertSame(
            ['111001', '111002', '111003', '111010', '111011', '111020', '111030', '111040'],
            Account::query()->where('is_cash_account', true)->orderBy('code')->pluck('code')->all(),
        );
    }

    #[Test]
    #[Group('VR-07')]
    public function the_advance_accounts_require_a_holder(): void
    {
        $this->assertSame(
            ['112010', '112020', '112021', '112030', '112032', '112035', '112037'],
            Account::query()->where('requires_advance_holder', true)->orderBy('code')->pluck('code')->all(),
        );
    }

    #[Test]
    public function custodian_accounts_belong_to_their_holder_and_responsibility_centre(): void
    {
        $account = Account::query()->where('code', '112035')->with(['holder', 'responsibilityCenter'])->firstOrFail();

        $this->assertSame('P-02', $account->holder?->code);
        $this->assertSame('RC-02', $account->responsibilityCenter?->code);
        $this->assertSame('P-01', Account::query()->where('code', '112101')->firstOrFail()->holder()->value('code'));
    }

    #[Test]
    #[Group('UAT-050')]
    public function the_conf_08_and_conf_09_corrections_are_logged_with_old_and_new_values(): void
    {
        $this->assertSame('Other Payables', Account::query()->where('code', '211090')->value('name'));
        $this->assertSame('Approved Administrative & Legal Expenses', Account::query()->where('code', '610019')->value('name'));

        $row = DB::table('audit_logs')
            ->where('table_name', 'accounts')
            ->where('field_name', 'name')
            ->where('action', 'change_request_apply')
            ->where('old_value', json_encode('Other Payables – Hassan Yaqoub'))
            ->first();

        $this->assertNotNull($row, 'The CONF-08 rename must be in the audit trail with its previous value.');
        $this->assertStringContainsString('CONF-08', (string) $row->reason);
    }

    #[Test]
    public function an_account_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);

        Account::query()->where('code', '610019')->delete();
    }
}
