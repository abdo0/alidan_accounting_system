<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** Document B §2.3: the structural, account, period, dimension, document and date rules. */
class ValidationRulesTest extends TestCase
{
    use BuildsJournals;

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     * @param  list<string>  $rules
     */
    private function assertSubmitRefused(string $type, array $lines, array $rules, array $header = []): void
    {
        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft($type, $lines, $header));
            $this->fail('Expected a refusal by '.implode(', ', $rules).'.');
        } catch (JournalRejected $rejected) {
            foreach ($rules as $rule) {
                $this->assertTrue($rejected->has($rule), "Expected {$rule}; got ".implode(', ', $rejected->rules()));
            }
        }
    }

    #[Test]
    #[Group('UAT-002')]
    #[Group('VR-01')]
    public function an_unbalanced_entry_is_refused(): void
    {
        $lines = $this->fundingLines(1_000);
        $lines[1]['credit'] = 999;

        $this->assertSubmitRefused('TT-01', $lines, ['VR-01']);
    }

    #[Test]
    #[Group('VR-02')]
    public function an_entry_needs_a_debit_and_a_credit(): void
    {
        $this->assertSubmitRefused('TT-01', [$this->fundingLines(1_000)[0]], ['VR-02']);
    }

    #[Test]
    #[Group('UAT-005')]
    #[Group('VR-03')]
    public function a_line_with_both_a_debit_and_a_credit_is_refused_on_save(): void
    {
        $lines = $this->fundingLines(1_000);
        $lines[0]['credit'] = 1_000;

        $this->expectException(JournalRejected::class);
        $this->draft('TT-01', $lines);
    }

    #[Test]
    #[Group('VR-19')]
    public function a_fractional_dinar_is_refused_not_rounded(): void
    {
        $lines = $this->fundingLines(1_000);
        $lines[0]['debit'] = '1000.5';
        $lines[1]['credit'] = '1000.5';

        try {
            $this->draft('TT-01', $lines);
            $this->fail('A fractional amount was accepted.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-19'));
        }
    }

    #[Test]
    #[Group('UAT-006')]
    public function a_multi_line_entry_with_one_debit_and_three_credits_posts(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(300));
        $posted = $this->postEntry('TT-06', [
            $this->line('111010', debit: 300, extra: ['cash_account_id' => $this->cashId('111010')]),
            $this->line('111002', credit: 100, extra: ['cash_account_id' => $this->cashId('111002')]),
            $this->line('111002', credit: 100, extra: ['cash_account_id' => $this->cashId('111002')]),
            $this->line('111002', credit: 100, extra: ['cash_account_id' => $this->cashId('111002')]),
        ]);

        $this->assertCount(4, $posted->lines);
    }

    #[Test]
    #[Group('UAT-003')]
    #[Group('VR-38')]
    public function a_non_posting_parent_is_refused(): void
    {
        $this->assertSubmitRefused('TT-22', [
            $this->line('115030', debit: 1_000, extra: ['capex_opex' => 'capex']),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId()]),
        ], ['VR-38']);
    }

    #[Test]
    #[Group('UAT-004')]
    #[Group('VR-04')]
    public function an_inactive_account_is_refused(): void
    {
        $this->assertSubmitRefused('TT-07', [
            $this->line('112031', debit: 1_000),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId()]),
        ], ['VR-04']);
    }

    #[Test]
    #[Group('UAT-007')]
    #[Group('VR-09')]
    public function nothing_posts_into_a_final_closed_period(): void
    {
        AccountingPeriod::query()->where('period_code', '2026-03')->update(['status' => PeriodStatus::FinalClose]);

        $this->assertSubmitRefused('TT-01', $this->fundingLines(1_000), ['VR-09']);
    }

    #[Test]
    #[Group('VR-09')]
    public function a_soft_closed_period_takes_only_the_finance_manager_with_a_reason(): void
    {
        $journal = $this->submitReviewApprove($this->draft('TT-01', $this->fundingLines(1_000), ['soft_close_reason' => 'Late bank advice']));
        AccountingPeriod::query()->where('period_code', '2026-03')->update(['status' => PeriodStatus::SoftClose]);

        $posted = app(JournalWorkflow::class)->post($this->manager(), $journal);

        $this->assertNotNull($posted->jv_no);
    }

    #[Test]
    #[Group('VR-12')]
    public function the_transaction_date_may_not_follow_the_posting_date(): void
    {
        $this->assertSubmitRefused('TT-01', $this->fundingLines(1_000), ['VR-12'], ['txn_date' => '2026-03-20']);
    }

    #[Test]
    #[Group('VR-05')]
    public function project_and_cost_centre_are_required_on_every_line(): void
    {
        $lines = $this->fundingLines(1_000);
        $lines[0]['cost_center_id'] = null;

        $this->assertSubmitRefused('TT-01', $lines, ['VR-05']);
    }

    #[Test]
    #[Group('VR-06')]
    public function a_cash_line_names_its_cash_account(): void
    {
        $lines = $this->fundingLines(1_000);
        unset($lines[0]['cash_account_id']);

        $this->assertSubmitRefused('TT-01', $lines, ['VR-06']);
    }

    #[Test]
    #[Group('VR-07')]
    public function an_advance_line_names_its_holder(): void
    {
        $this->assertSubmitRefused('TT-07', [
            $this->line('112030', debit: 1_000, extra: ['settlement_deadline' => '2026-06-30']),
            $this->line('111002', credit: 1_000, extra: ['cash_account_id' => $this->cashId()]),
        ], ['VR-07']);
    }

    #[Test]
    #[Group('VR-08')]
    public function a_shareholder_line_names_its_counterparty(): void
    {
        $lines = $this->fundingLines(1_000);
        unset($lines[1]['counterparty_id']);

        $this->assertSubmitRefused('TT-01', $lines, ['VR-08']);
    }

    #[Test]
    #[Group('VR-13')]
    public function missing_documents_must_be_acknowledged(): void
    {
        $journal = $this->draft('TT-01', $this->fundingLines(1_000), ['doc_status' => 'missing', 'doc_ref' => null]);

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $journal);
            $this->fail('A Missing document status passed without acknowledgement.');
        } catch (JournalRejected $rejected) {
            $this->assertSame(['VR-13'], $rejected->rules());
        }

        $submitted = app(JournalWorkflow::class)->submit($this->maker(), $journal, ['VR-13']);
        $this->assertSame(['VR-13'], $submitted->acknowledged_warnings);
    }

    #[Test]
    #[Group('VR-14')]
    public function a_complete_document_needs_its_reference(): void
    {
        $this->expectException(JournalRejected::class);

        $this->draft('TT-01', $this->fundingLines(1_000), ['doc_status' => 'complete', 'doc_ref' => null]);
    }

    #[Test]
    #[Group('VR-15')]
    #[Group('UAT-042')]
    public function an_undated_entry_keeps_its_status_and_no_date_is_supplied(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000), ['txn_date' => null, 'date_status' => 'no_date_in_source']);

        $this->assertNull($posted->txn_date);
        $this->assertSame('no_date_in_source', $posted->date_status->value);

        $this->assertSubmitRefused('TT-01', $this->fundingLines(1_000), ['VR-15'], ['txn_date' => '2026-03-01', 'date_status' => 'no_date_in_source']);
    }
}
