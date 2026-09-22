<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\Posting\ReclassificationService;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Account;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/**
 * The funding chain and advance rules that the authoritative workbook shows going
 * wrong without them (Document B §2.4, §2.6).
 */
class PostingRulesTest extends TestCase
{
    use BuildsJournals;

    /** @param  list<string>  $rules */
    private function assertRefused(callable $attempt, array $rules): void
    {
        try {
            $attempt();
            $this->fail('Expected a refusal by '.implode(', ', $rules).'.');
        } catch (JournalRejected $rejected) {
            foreach ($rules as $rule) {
                $this->assertTrue($rejected->has($rule), "Expected {$rule}; got ".implode(', ', $rejected->rules()));
            }
        }
    }

    /** @return array<string, mixed> */
    private function capexDims(): array
    {
        return ['capex_opex' => 'capex', 'asset_class' => 'Concession CIP', 'handover_req' => 'Handover Required at Expiry'];
    }

    /** @return array<string, mixed> */
    private function custodyDims(string $holder = 'P-01', string $rc = 'RC-01'): array
    {
        return ['advance_holder_id' => $this->counterpartyId($holder), 'counterparty_id' => $this->counterpartyId($holder), 'resp_center_id' => $this->rcId($rc)];
    }

    #[Test]
    #[Group('UAT-013')]
    #[Group('VR-11')]
    public function a_shareholder_paying_a_supplier_directly_is_a_loan_and_a_capex_cost(): void
    {
        $posted = $this->postEntry('TT-02', [
            $this->line('115050', debit: 50_000_000, extra: ['counterparty_id' => $this->counterpartyId('CN-03')] + $this->capexDims()),
            $this->line('221001', credit: 50_000_000, extra: ['counterparty_id' => $this->counterpartyId('SH-01')]),
        ]);

        $this->assertSame('PR-04', $posted->postingRule?->code);

        $this->assertRefused(fn () => $this->submitReviewApprove($this->draft('TT-02', [
            $this->line('115050', debit: 1_000, extra: ['counterparty_id' => $this->counterpartyId('CN-03')] + $this->capexDims()),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId()]),
        ])), ['VR-11']);
    }

    #[Test]
    #[Group('UAT-014')]
    #[Group('VR-20')]
    public function spending_project_funds_never_reduces_the_shareholder_loan(): void
    {
        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-05', [
            $this->line('221001', debit: 1_000, extra: ['counterparty_id' => $this->counterpartyId('SH-01')]),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId()]),
        ])), ['VR-20']);
    }

    #[Test]
    #[Group('UAT-015')]
    #[Group('VR-21')]
    public function a_third_party_funder_is_never_credited_to_a_shareholder_loan(): void
    {
        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-03', [
            $this->line('111002', debit: 7_125_000, extra: ['cash_account_id' => $this->cashId()]),
            $this->line('221001', credit: 7_125_000, extra: ['counterparty_id' => $this->counterpartyId('TP-01')]),
        ], ['approval_ref' => 'JV-1028 board decision'])), ['VR-21', 'PR']);

        $posted = $this->postEntry('TT-03', [
            $this->line('111002', debit: 7_125_000, extra: ['cash_account_id' => $this->cashId()]),
            $this->line('211090', credit: 7_125_000, extra: ['counterparty_id' => $this->counterpartyId('TP-01')]),
        ], ['approval_ref' => 'JV-1028 board decision']);

        $this->assertSame(JournalStatus::Posted, $posted->status);
    }

    #[Test]
    #[Group('UAT-016')]
    #[Group('UAT-018')]
    #[Group('VR-26')]
    public function an_advance_is_issued_then_settled_to_capex_by_crediting_the_advance(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(500_000_000));

        $issue = $this->postEntry('TT-07', [
            $this->line('112030', debit: 200_000_000, extra: $this->custodyDims() + ['settlement_deadline' => '2026-06-30']),
            $this->line('111002', credit: 200_000_000, extra: ['cash_account_id' => $this->cashId(), 'resp_center_id' => $this->rcId('RC-01')]),
        ]);
        $this->assertSame('PR-07', $issue->postingRule?->code);

        $settlement = $this->postEntry('TT-09', [
            $this->line('115050', debit: 200_000_000, extra: ['counterparty_id' => $this->counterpartyId('CN-03'), 'resp_center_id' => $this->rcId('RC-01')] + $this->capexDims()),
            $this->line('112030', credit: 200_000_000, extra: $this->custodyDims()),
        ]);

        $this->assertSame('PR-09', $settlement->postingRule?->code);
    }

    #[Test]
    #[Group('UAT-017')]
    #[Group('VR-25')]
    public function issuing_an_advance_may_not_be_expensed(): void
    {
        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-07', [
            $this->line('610003', debit: 1_000, extra: ['resp_center_id' => $this->rcId('RC-01')]),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId(), 'resp_center_id' => $this->rcId('RC-01')]),
        ])), ['VR-25']);
    }

    #[Test]
    #[Group('UAT-019')]
    #[Group('VR-27')]
    public function an_advance_may_not_be_settled_by_crediting_an_expense(): void
    {
        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-10', [
            $this->line('610003', debit: 100, extra: ['capex_opex' => 'opex', 'counterparty_id' => $this->counterpartyId('CN-05'), 'resp_center_id' => $this->rcId('RC-01')]),
            $this->line('112030', credit: 60, extra: $this->custodyDims()),
            $this->line('610004', credit: 40, extra: ['capex_opex' => 'opex', 'resp_center_id' => $this->rcId('RC-01')]),
        ])), ['VR-27']);
    }

    #[Test]
    #[Group('UAT-020')]
    #[Group('VR-28')]
    public function unsupported_custody_spend_becomes_a_named_personal_receivable(): void
    {
        $lines = [
            $this->line('112101', debit: 62_528_000, extra: ['counterparty_id' => $this->counterpartyId('P-01'), 'resp_center_id' => $this->rcId('RC-01')]),
            $this->line('112030', credit: 62_528_000, extra: $this->custodyDims()),
        ];

        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-12', $lines)), ['VR-28']);

        $posted = $this->postEntry('TT-12', $lines, ['approval_ref' => 'MGMT-DET-2025-03']);

        $this->assertSame('PR-11', $posted->postingRule?->code);
    }

    #[Test]
    #[Group('UAT-036')]
    #[Group('VR-24')]
    public function cash_moves_between_two_cash_accounts_but_never_to_itself(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(10_000));

        $posted = $this->postEntry('TT-06', [
            $this->line('111010', debit: 5_000, extra: ['cash_account_id' => $this->cashId('111010')]),
            $this->line('111002', credit: 5_000, extra: ['cash_account_id' => $this->cashId('111002')]),
        ]);
        $this->assertSame('PR-33', $posted->postingRule?->code);

        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-06', [
            $this->line('111002', debit: 5_000, extra: ['cash_account_id' => $this->cashId('111002')]),
            $this->line('111002', credit: 5_000, extra: ['cash_account_id' => $this->cashId('111002')]),
        ])), ['VR-24']);
    }

    #[Test]
    #[Group('UAT-043')]
    #[Group('VR-49')]
    #[Group('VR-50')]
    public function an_approved_reclassification_changes_only_the_account(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(40_000_000));
        $original = $this->postEntry('TT-05', [
            $this->line('115099', debit: 30_000_000, extra: ['counterparty_id' => $this->counterpartyId('CN-02'), 'capex_opex' => 'capex']),
            $this->line('111002', credit: 30_000_000, extra: ['cash_account_id' => $this->cashId()]),
        ]);

        $draft = app(ReclassificationService::class)->prepare(
            $this->maker(),
            $original->lines->firstWhere('debit', '>', 0),
            Account::query()->where('code', '115050')->firstOrFail(),
            'BOARD-RECLASS-17',
        );

        $posted = app(JournalWorkflow::class)->post($this->manager(), $this->submitReviewApprove($draft));

        $this->assertSame('PR-30', $posted->postingRule?->code);
        $this->assertSame($original->id, $posted->linked_journal_id);
        $this->assertSame($original->description_ar, $posted->description_ar);
        $this->assertSame(JournalStatus::Posted, $original->fresh()->status, 'The original stays posted; the correction is a new entry.');
    }

    #[Test]
    #[Group('VR-49')]
    public function a_reclassification_never_credits_an_advance_a_second_time(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(1_000));
        $issue = $this->postEntry('TT-07', [
            $this->line('112030', debit: 1_000, extra: $this->custodyDims() + ['settlement_deadline' => '2026-06-30']),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId(), 'resp_center_id' => $this->rcId('RC-01')]),
        ]);

        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-34', [
            $this->line('115050', debit: 1_000, extra: $this->capexDims()),
            $this->line('112030', credit: 1_000, extra: $this->custodyDims()),
        ], ['linked_journal_id' => $issue->id, 'approval_ref' => 'X', 'description_ar' => $issue->description_ar])), ['VR-49']);
    }

    #[Test]
    public function a_pending_derived_rule_blocks_its_transaction_type(): void
    {
        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-04', [
            $this->line('111002', debit: 1_000, extra: ['cash_account_id' => $this->cashId(), 'counterparty_id' => $this->counterpartyId('CN-05')]),
            $this->line('112040', credit: 1_000),
        ])), ['PR']);
    }

    #[Test]
    #[Group('PR-DIM')]
    public function the_posting_rule_demands_its_mandatory_dimensions(): void
    {
        $lines = $this->fundingLines(1_000);
        unset($lines[1]['funding_source_id']);

        $this->assertRefused(fn () => app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-01', $lines)), ['PR-DIM']);
        $this->assertNotNull(FundingSource::query()->where('code', 'FS-SH-01')->first());
    }
}
