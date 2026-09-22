<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterService;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\Result\Row;
use App\Domain\Revenue\GovernmentShareService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M12 concession and CIP, M14 revenue and the government share. */
class ConcessionAndRevenueTest extends TestCase
{
    use BuildsJournals;

    /** @return list<array<string, mixed>> */
    private function cipTransfer(int $amount): array
    {
        return [
            $this->line('117001', debit: $amount, extra: ['asset_class' => 'Concession Right']),
            $this->line('115050', credit: $amount, extra: ['asset_class' => 'Concession CIP', 'handover_req' => 'Handover Required at Expiry', 'capex_opex' => 'capex']),
        ];
    }

    private function capitalise(int $amount): void
    {
        $this->postEntry('TT-01', $this->fundingLines($amount));
        $this->postEntry('TT-05', [
            $this->line('115050', debit: $amount, extra: ['counterparty_id' => $this->counterpartyId('CN-03'), 'capex_opex' => 'capex']),
            $this->line('111002', credit: $amount, extra: ['cash_account_id' => $this->cashId()]),
        ]);
    }

    #[Test]
    #[Group('UAT-026')]
    public function a_capitalised_cost_appears_in_the_cip_register(): void
    {
        $this->capitalise(80_000_000);

        $cip = app(ReportRegistry::class)->get('RPT-18')->run(FilterSet::fromArray(['from' => '2026-01-01', 'to' => '2026-12-31']), $this->manager());
        $this->assertSame(80_000_000, $cip->rowsOfStyle(Row::TOTAL)[0]->cells['additions']);
    }

    #[Test]
    #[Group('UAT-027')]
    #[Group('VR-39')]
    public function cip_transfer_is_blocked_while_the_operation_date_is_unset(): void
    {
        $this->capitalise(1_000);

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-23', $this->cipTransfer(1_000), ['approval_ref' => 'BOARD-1']));
            $this->fail('A CIP transfer passed with no commercial operation date.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-39'));
        }
    }

    #[Test]
    #[Group('UAT-028')]
    #[Group('UAT-029')]
    #[Group('VR-40')]
    public function once_the_date_is_set_transfer_is_allowed_and_amortization_waits_for_it(): void
    {
        $this->capitalise(1_000);
        app(ParameterService::class)->change($this->manager(), ParameterCode::CommercialOperationDate, '2026-03-01', CarbonImmutable::parse('2026-02-01'), 'BOARD-COD-1', 'Operation certificate issued');

        $posted = $this->postEntry('TT-23', $this->cipTransfer(1_000), ['approval_ref' => 'BOARD-TRANSFER-1']);
        $this->assertNotNull($posted->jv_no);

        app(ParameterService::class)->change($this->manager(), ParameterCode::CommercialOperationDate, '2026-06-01', CarbonImmutable::parse('2026-02-15'), 'BOARD-COD-2', 'Date revised');

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-24', [
                $this->line('610017', debit: 100, extra: ['capex_opex' => 'opex']),
                $this->line('117011', credit: 100),
            ]));
            $this->fail('Amortization dated before the operation date passed.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-40'));
        }
    }

    private function earn(int $amount, bool $eligible = true, ?string $reason = null): void
    {
        $journal = $this->draft('TT-27', [
            $this->line('111002', debit: $amount, extra: ['cash_account_id' => $this->cashId()]),
            $this->line('410001', credit: $amount, extra: ['revenue_eligible' => $eligible, 'eligibility_reason' => $reason, 'counterparty_id' => $this->counterpartyId('CN-05')]),
        ]);
        app(JournalWorkflow::class)->post($this->manager(), $this->submitReviewApprove($journal));
    }

    #[Test]
    #[Group('UAT-030')]
    #[Group('UAT-031')]
    #[Group('UAT-032')]
    #[Group('VR-43')]
    public function the_share_is_computed_on_eligible_revenue_never_on_funding(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(500_000_000));
        $this->earn(100_000_000);

        $period = AccountingPeriod::query()->where('period_code', '2026-03')->firstOrFail();
        $figures = app(GovernmentShareService::class)->compute($period);

        $this->assertSame(100_000_000, $figures['eligible']);
        $this->assertSame(20_000_000, $figures['due'], 'Not 120,000,000: funding is not revenue.');

        $draft = app(GovernmentShareService::class)->prepareRecognition($this->maker(), $period, $this->projectId(), $this->costCenterId());
        app(JournalWorkflow::class)->post($this->manager(), $this->submitReviewApprove($draft));

        $rpt = app(ReportRegistry::class)->get('RPT-19')->run(FilterSet::fromArray(['from' => '2026-03-01', 'to' => '2026-03-31']), $this->manager());
        $this->assertTrue($rpt->controlsPass());
    }

    #[Test]
    #[Group('UAT-033')]
    public function a_new_rate_applies_from_its_effective_date_only(): void
    {
        $this->earn(100_000_000);
        app(ParameterService::class)->change($this->manager(), ParameterCode::GovShareRate, '0.25', CarbonImmutable::parse('2026-04-01'), 'BOARD-RATE', 'Amendment');

        $march = app(GovernmentShareService::class)->compute(AccountingPeriod::query()->where('period_code', '2026-03')->firstOrFail());
        $this->assertSame('0.20', $march['rate']);
    }

    #[Test]
    #[Group('VR-44')]
    public function excluded_revenue_needs_a_reason_and_stays_out_of_the_base(): void
    {
        try {
            $this->earn(10_000_000, eligible: false);
            $this->fail('An excluded revenue line passed without a reason.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-44'));
        }

        $this->earn(10_000_000, eligible: false, reason: 'Asset disposal');
        $figures = app(GovernmentShareService::class)->compute(AccountingPeriod::query()->where('period_code', '2026-03')->firstOrFail());
        $this->assertSame(0, $figures['due']);
        $this->assertSame(10_000_000, $figures['excluded']);
    }
}
