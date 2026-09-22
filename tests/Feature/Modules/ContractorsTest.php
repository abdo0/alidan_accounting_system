<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\Funding\FundingBatch;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\WorkPackage;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M06: contractors and suppliers (Document B §4.5). */
class ContractorsTest extends TestCase
{
    use BuildsJournals;

    private Contract $contract;

    private int $workPackage;

    private int $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contract = Contract::query()->create([
            'contract_no' => 'CT-0001', 'title' => 'Yard civil works', 'contract_type' => 'construction',
            'counterparty_id' => $this->counterpartyId('CN-02'), 'contract_value' => 5_000_000_000,
            'retention_pct' => 10, 'advance_recovery_pct' => 20, 'status' => 'active',
        ]);

        $this->workPackage = WorkPackage::query()->create([
            'contract_id' => $this->contract->id, 'code' => 'WP-01', 'name' => 'Earthworks',
        ])->id;
        $this->batch = FundingBatch::query()->create(['batch_ref' => 'TAJ-DAR-01', 'status' => 'posted'])->id;

        $this->postEntry('TT-01', $this->fundingLines(3_000_000_000));
    }

    /** @return array<string, mixed> */
    private function party(): array
    {
        return ['counterparty_id' => $this->counterpartyId('CN-02'), 'contract_id' => $this->contract->id, 'work_package_id' => $this->workPackage];
    }

    private function advance(int $amount): void
    {
        $this->postEntry('TT-14', [
            $this->line('112020', debit: $amount, extra: $this->party() + ['advance_holder_id' => $this->counterpartyId('CN-02'), 'funding_batch_id' => $this->batch]),
            $this->line('111002', credit: $amount, extra: ['cash_account_id' => $this->cashId()]),
        ]);
    }

    private function certify(int $amount): void
    {
        $this->postEntry('TT-15', [
            $this->line('115032', debit: $amount, extra: $this->party() + ['capex_opex' => 'capex']),
            $this->line('211003', credit: $amount, extra: $this->party()),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $rules
     */
    private function refused(string $type, array $lines, array $rules): void
    {
        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft($type, $lines));
            $this->fail('Expected '.implode(', ', $rules));
        } catch (JournalRejected $e) {
            foreach ($rules as $rule) {
                $this->assertTrue($e->has($rule), "Expected {$rule}; got ".implode(', ', $e->rules()));
            }
        }
    }

    #[Test]
    #[Group('UAT-022')]
    #[Group('UAT-023')]
    #[Group('VR-32')]
    public function an_advance_is_recovered_against_certified_work_but_never_beyond_itself(): void
    {
        $this->advance(1_000_000_000);
        $this->certify(2_000_000_000);

        $this->postEntry('TT-16', [
            $this->line('211003', debit: 900_000_000, extra: $this->party()),
            $this->line('112020', credit: 900_000_000, extra: $this->party()),
        ]);

        $this->refused('TT-16', [
            $this->line('211003', debit: 200_000_000, extra: $this->party()),
            $this->line('112020', credit: 200_000_000, extra: $this->party()),
        ], ['VR-32']);
    }

    #[Test]
    #[Group('VR-31')]
    public function certification_may_not_exceed_the_contract(): void
    {
        $this->certify(4_000_000_000);

        $this->refused('TT-15', [
            $this->line('115032', debit: 1_500_000_000, extra: $this->party() + ['capex_opex' => 'capex']),
            $this->line('211003', credit: 1_500_000_000, extra: $this->party()),
        ], ['VR-31']);
    }

    #[Test]
    #[Group('UAT-024')]
    #[Group('VR-33')]
    public function retention_that_differs_from_the_contract_is_a_warning(): void
    {
        $this->certify(1_000_000_000);

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-17', [
                $this->line('211003', debit: 50_000_000, extra: $this->party()),
                $this->line('211004', credit: 50_000_000, extra: $this->party()),
            ]));
            $this->fail('A retention of 5% passed without a warning.');
        } catch (JournalRejected $e) {
            $this->assertSame(['VR-33'], $e->rules());
        }

        $this->postEntry('TT-17', [
            $this->line('211003', debit: 100_000_000, extra: $this->party()),
            $this->line('211004', credit: 100_000_000, extra: $this->party()),
        ]);

        $this->assertSame(100_000_000, app(SubledgerQuery::class)->figures($this->counterpartyId('CN-02'))['retention']);
    }

    #[Test]
    #[Group('UAT-025')]
    #[Group('VR-34')]
    public function a_payment_may_not_exceed_the_outstanding_payable(): void
    {
        $this->certify(500_000_000);

        $this->refused('TT-18', [
            $this->line('211003', debit: 600_000_000, extra: $this->party()),
            $this->line('111002', credit: 600_000_000, extra: ['cash_account_id' => $this->cashId()]),
        ], ['VR-34']);
    }

    #[Test]
    public function the_subledger_agrees_to_the_general_ledger(): void
    {
        $this->advance(1_000_000_000);
        $this->certify(2_000_000_000);

        $recon = app(ReportRegistry::class)->get('RPT-17')->run(FilterSet::fromArray(['as_at' => '2026-12-31']), $this->manager());
        $this->assertTrue($recon->controlsPass());

        $sub = app(ReportRegistry::class)->get('RPT-16')->run(FilterSet::fromArray(['as_at' => '2026-12-31']), $this->manager());
        $row = $sub->findRow('party', Counterparty::query()->where('code', 'CN-02')->firstOrFail()->label());
        $this->assertNotNull($row);
        $this->assertSame(2_000_000_000, $row->cells['payable']);
        $this->assertSame(3_000_000_000, $row->cells['commitment']);
    }
}
