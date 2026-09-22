<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingCategory;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\Shareholder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** M01, M02. Master data loaded from Document C tabs 05-10 (MIG-02 ... MIG-09). */
class MasterDataSeedTest extends TestCase
{
    #[Test]
    #[Group('MIG-02')]
    public function the_four_projects_including_prj_03(): void
    {
        $this->assertSame(['CORP', 'PRJ-01', 'PRJ-02', 'PRJ-03'], Project::query()->orderBy('code')->pluck('code')->all());
    }

    #[Test]
    #[Group('MIG-03')]
    public function nineteen_functional_cost_centres(): void
    {
        $this->assertSame(19, CostCenter::query()->count());
        $this->assertTrue(CostCenter::query()->where('code', 'CC-100D')->exists());
    }

    #[Test]
    #[Group('MIG-04')]
    public function three_responsibility_centres_linked_to_their_custodians(): void
    {
        $this->assertSame(['RC-01', 'RC-02', 'RC-CORP'], ResponsibilityCenter::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame('P-01', ResponsibilityCenter::query()->where('code', 'RC-01')->firstOrFail()->linkedCounterparty?->code);
    }

    #[Test]
    #[Group('MIG-05')]
    public function each_shareholder_owns_exactly_its_loan_current_and_capital_accounts(): void
    {
        $this->assertSame(4, Shareholder::query()->count());

        $sh01 = Shareholder::query()->where('code', 'SH-01')->with(['loanAccount', 'currentAccount', 'capitalAccount'])->firstOrFail();

        $this->assertSame('221001', $sh01->loanAccount?->code);
        $this->assertSame('222001', $sh01->currentAccount?->code);
        $this->assertSame('310001', $sh01->capitalAccount?->code);
        $this->assertTrue($sh01->is_approved_financier);
    }

    #[Test]
    #[Group('MIG-07')]
    public function gulf_shield_is_merged_into_deraa_al_khaleej_as_an_alias(): void
    {
        $this->assertFalse(Counterparty::query()->where('code', 'CN-11')->exists());

        $deraa = Counterparty::query()->where('code', 'CN-04')->with('aliases')->firstOrFail();
        $this->assertSame('CN-11', $deraa->aliases->first()?->legacy_code);
    }

    #[Test]
    public function the_parties_named_elsewhere_in_the_specification_exist(): void
    {
        $this->assertTrue(Counterparty::query()->where('name', 'Hassan Yaqoub')->exists(), 'CONF-08');
        $this->assertTrue(Counterparty::query()->where('code', 'GOV-01')->where('is_government', true)->exists(), 'PARAM-011');
    }

    #[Test]
    #[Group('MIG-08')]
    public function exactly_one_cash_record_per_cash_account(): void
    {
        $this->assertSame(8, BankAccount::query()->count());
        $this->assertSame(
            Account::query()->where('is_cash_account', true)->orderBy('id')->pluck('id')->all(),
            BankAccount::query()->orderBy('account_id')->pluck('account_id')->all(),
        );
    }

    #[Test]
    #[Group('MIG-09')]
    public function the_concession_contract_loads_pending_with_no_invented_value(): void
    {
        $contract = Contract::query()->where('contract_no', 'CONC-01')->firstOrFail();

        $this->assertNull($contract->contract_value);
        $this->assertNull($contract->signed_on);
        $this->assertContains('contract_value', $contract->pending_fields);
    }

    #[Test]
    public function funding_reference_data_flags_journal_only_values_for_approval(): void
    {
        $this->assertSame(11, FundingCategory::query()->count());
        $this->assertSame(
            ['FC-10', 'FC-11'],
            FundingCategory::query()->where('approval_status', ApprovalStatus::Pending)->orderBy('code')->pluck('code')->all(),
        );
        $this->assertSame(12, ChainStep::query()->count());
    }
}
