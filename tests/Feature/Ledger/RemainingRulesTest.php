<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\Posting\ReversalService;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Enums\NormalBalance;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** The rules of Document C tab 18 not exercised by the module tests. */
class RemainingRulesTest extends TestCase
{
    use BuildsJournals;

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     * @param  list<string>  $acknowledge
     */
    private function refusedWith(string $rule, string $type, array $lines, array $header = [], array $acknowledge = []): void
    {
        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft($type, $lines, $header), $acknowledge);
            $this->fail("Expected {$rule}.");
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has($rule), "Expected {$rule}; got ".implode(', ', $e->rules()));
        }
    }

    #[Test]
    #[Group('VR-22')]
    public function revenue_is_credited_only_to_the_approved_revenue_accounts(): void
    {
        $stray = Account::query()->create([
            'code' => '410050', 'parent_id' => Account::query()->where('code', '410000')->value('id'), 'name' => 'Test revenue',
            'account_type' => AccountType::Revenue, 'account_level' => 2, 'is_posting' => true, 'is_active' => true,
            'normal_balance' => NormalBalance::Credit, 'fs_line_code' => 'PL-010',
        ]);

        $this->refusedWith('VR-22', 'TT-27', [
            $this->line('111002', debit: 100, extra: ['cash_account_id' => $this->cashId()]),
            ['account_id' => $stray->id, 'debit' => 0, 'credit' => 100, 'project_id' => $this->projectId(), 'cost_center_id' => $this->costCenterId(), 'revenue_eligible' => true],
        ]);
    }

    #[Test]
    #[Group('VR-23')]
    public function both_legs_of_a_cash_transfer_are_cash_accounts(): void
    {
        $this->refusedWith('VR-23', 'TT-06', [
            $this->line('112050', debit: 100, extra: ['counterparty_id' => $this->counterpartyId('CN-05')]),
            $this->line('111002', credit: 100, extra: ['cash_account_id' => $this->cashId()]),
        ]);
    }

    #[Test]
    #[Group('VR-29')]
    public function a_contractor_advance_names_its_contract(): void
    {
        $this->refusedWith('VR-29', 'TT-14', [
            $this->line('112020', debit: 100, extra: ['counterparty_id' => $this->counterpartyId('CN-02'), 'advance_holder_id' => $this->counterpartyId('CN-02')]),
            $this->line('111002', credit: 100, extra: ['cash_account_id' => $this->cashId()]),
        ]);
    }

    #[Test]
    #[Group('VR-35')]
    public function the_same_supplier_invoice_is_not_recognised_twice(): void
    {
        $lines = [
            $this->line('610006', debit: 1_000, extra: ['counterparty_id' => $this->counterpartyId('CN-05'), 'capex_opex' => 'opex']),
            $this->line('211001', credit: 1_000, extra: ['counterparty_id' => $this->counterpartyId('CN-05')]),
        ];
        $this->postEntry('TT-19', $lines, ['doc_ref' => 'INV-77']);

        $lines[0]['debit'] = 2_000;
        $lines[1]['credit'] = 2_000;
        $this->refusedWith('VR-35', 'TT-19', $lines, ['doc_ref' => 'INV-77']);
    }

    #[Test]
    #[Group('VR-36')]
    public function an_acquisition_payment_is_never_expensed(): void
    {
        $this->refusedWith('VR-36', 'TT-20', [
            $this->line('610003', debit: 100, extra: ['project_id' => $this->projectId('PRJ-02'), 'counterparty_id' => $this->counterpartyId('P-01')]),
            $this->line('111002', credit: 100, extra: ['project_id' => $this->projectId('PRJ-02'), 'cash_account_id' => $this->cashId()]),
        ], ['approval_ref' => 'BOARD-9']);
    }

    #[Test]
    #[Group('VR-37')]
    public function completing_an_acquisition_needs_the_board_resolution(): void
    {
        $this->refusedWith('VR-37', 'TT-21', [
            $this->line('116002', debit: 100, extra: ['project_id' => $this->projectId('PRJ-02'), 'counterparty_id' => $this->counterpartyId('P-01'), 'asset_class' => 'Advance']),
            $this->line('116001', credit: 100, extra: ['project_id' => $this->projectId('PRJ-02'), 'counterparty_id' => $this->counterpartyId('P-01'), 'asset_class' => 'Advance']),
        ]);
    }

    #[Test]
    #[Group('VR-41')]
    public function depreciation_runs_once_per_period(): void
    {
        $lines = [
            $this->line('610016', debit: 500, extra: ['capex_opex' => 'opex']),
            $this->line('118011', credit: 500),
        ];

        $this->postEntry('TT-26', $lines);
        $this->refusedWith('VR-41', 'TT-26', $lines);
    }

    #[Test]
    #[Group('VR-42')]
    public function a_revenue_line_states_its_eligibility(): void
    {
        $this->refusedWith('VR-42', 'TT-27', [
            $this->line('111002', debit: 100, extra: ['cash_account_id' => $this->cashId()]),
            $this->line('410001', credit: 100, extra: ['counterparty_id' => $this->counterpartyId('CN-05')]),
        ]);
    }

    #[Test]
    #[Group('VR-45')]
    public function an_accrual_asks_for_its_reversal_plan(): void
    {
        $this->refusedWith('VR-45', 'TT-30', [
            $this->line('610001', debit: 100, extra: ['capex_opex' => 'opex']),
            $this->line('211011', credit: 100),
        ]);
    }

    #[Test]
    #[Group('VR-46')]
    public function a_prepayment_release_never_exceeds_what_is_carried(): void
    {
        $this->refusedWith('VR-46', 'TT-31', [
            $this->line('610013', debit: 1_000, extra: ['capex_opex' => 'opex']),
            $this->line('113002', credit: 1_000),
        ]);
    }

    #[Test]
    #[Group('VR-52')]
    public function a_reversal_posts_only_into_an_open_period(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000));
        AccountingPeriod::query()->where('period_code', '2026-04')->update(['status' => PeriodStatus::FinalClose]);

        try {
            app(ReversalService::class)->reverse($this->manager(), $posted, 'wrong', CarbonImmutable::parse('2026-04-10'));
            $this->fail('A reversal went into a closed period.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-52'));
        }
    }
}
