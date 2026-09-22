<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvanceClaimService;
use App\Domain\Advances\AdvancePosition;
use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Advances\OverdueAdvances;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\Result\Row;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M04: advances and custodians (Document B §4.3). */
class AdvancesTest extends TestCase
{
    use BuildsJournals;

    /** @return array<string, mixed> */
    private function custody(): array
    {
        return ['advance_holder_id' => $this->counterpartyId('P-01'), 'counterparty_id' => $this->counterpartyId('P-01'), 'resp_center_id' => $this->rcId('RC-01')];
    }

    private function issue(int $amount, string $deadline = '2026-06-30'): Advance
    {
        $this->postEntry('TT-01', $this->fundingLines($amount));
        $this->postEntry('TT-07', [
            $this->line('112030', debit: $amount, extra: $this->custody() + ['settlement_deadline' => $deadline]),
            $this->line('111002', credit: $amount, extra: ['cash_account_id' => $this->cashId(), 'resp_center_id' => $this->rcId('RC-01')]),
        ]);

        return Advance::query()->latest('id')->firstOrFail();
    }

    private function settleCapex(int $amount): void
    {
        $this->postEntry('TT-09', [
            $this->line('115050', debit: $amount, extra: ['counterparty_id' => $this->counterpartyId('CN-03'), 'resp_center_id' => $this->rcId('RC-01'), 'capex_opex' => 'capex', 'asset_class' => 'Concession CIP', 'handover_req' => 'Handover Required at Expiry']),
            $this->line('112030', credit: $amount, extra: $this->custody()),
        ]);
    }

    #[Test]
    #[Group('UAT-016')]
    public function issuing_an_advance_opens_it_in_the_register(): void
    {
        $advance = $this->issue(200_000_000);

        $this->assertSame($this->counterpartyId('P-01'), $advance->holder_id);
        $this->assertSame('2026-06-30', $advance->settlement_deadline?->toDateString());
        $this->assertSame(200_000_000, AdvancePosition::of($advance, now())->outstanding);
    }

    #[Test]
    #[Group('UAT-018')]
    public function settlements_reduce_the_outstanding_amount_derived_from_the_ledger(): void
    {
        $advance = $this->issue(200_000_000);
        $this->settleCapex(150_000_000);

        $position = AdvancePosition::of($advance, now());

        $this->assertSame(150_000_000, $position->settled(SettlementType::Capex));
        $this->assertSame(50_000_000, $position->outstanding);

        $this->settleCapex(50_000_000);
        $this->assertSame(AdvanceStatus::Settled, AdvancePosition::of($advance, now())->status());
    }

    #[Test]
    public function a_pending_evidence_claim_leaves_the_amount_in_the_advance(): void
    {
        $advance = $this->issue(10_000_000);

        app(AdvanceClaimService::class)->recordPendingClaim($this->reviewer(), $advance, 4_000_000, 'Receipts not yet provided');

        $position = AdvancePosition::of($advance, now());
        $this->assertSame(10_000_000, $position->outstanding);
        $this->assertSame(4_000_000, $position->pendingClaims);
        $this->assertTrue(ControlException::query()->where('category', ExceptionCategory::PendingEvidence)->exists());
    }

    #[Test]
    #[Group('UAT-021')]
    public function an_advance_past_its_deadline_is_overdue_and_raises_an_exception(): void
    {
        $advance = $this->issue(1_000_000, '2026-03-20');
        $this->travelTo('2026-05-01');

        $this->assertSame(1, app(OverdueAdvances::class)->flag());
        $this->assertSame(AdvanceStatus::Overdue, AdvancePosition::of($advance)->status());
        $this->assertTrue(ControlException::query()->where('dedupe_key', 'overdue:'.$advance->id)->exists());

        app(OverdueAdvances::class)->flag();
        $this->assertSame(1, ControlException::query()->where('dedupe_key', 'overdue:'.$advance->id)->count(), 'Raised once.');
    }

    #[Test]
    public function the_register_agrees_to_the_ledger_and_the_aging_buckets_it(): void
    {
        $this->issue(300_000_000);
        $this->settleCapex(100_000_000);

        $rpt13 = app(ReportRegistry::class)->get('RPT-13')->run(FilterSet::fromArray(['as_at' => '2026-12-31']), $this->manager());
        $this->assertTrue($rpt13->controlsPass());

        $rpt14 = app(ReportRegistry::class)->get('RPT-14')->run(FilterSet::fromArray(['as_at' => '2026-04-30']), $this->manager());
        $this->assertSame(200_000_000, $rpt14->rowsOfStyle(Row::TOTAL)[0]->cells['outstanding']);
    }

    #[Test]
    public function the_funding_chain_reports_the_advance_difference_at_its_true_value(): void
    {
        $this->issue(100_000_000);
        $this->settleCapex(60_000_000);

        $chain = app(ReportRegistry::class)->get('RPT-20')->run(FilterSet::fromArray(['from' => '2026-01-01', 'to' => '2026-12-31']), $this->manager());

        $this->assertSame(40_000_000, $chain->controls[0]->difference());
        $this->assertFalse($chain->controlsPass(), 'The difference is shown, never forced to zero.');
    }
}
