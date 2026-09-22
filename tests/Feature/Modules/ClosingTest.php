<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Closing\Enums\ChecklistStatus;
use App\Domain\Closing\PeriodCloseService;
use App\Domain\Ledger\JournalService;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;
use App\Domain\Shared\Exceptions\RuleViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M15: period management and closing (Document B §4.11). */
class ClosingTest extends TestCase
{
    use BuildsJournals;

    private function period(string $code): AccountingPeriod
    {
        return AccountingPeriod::query()->where('period_code', $code)->firstOrFail();
    }

    private function completeChecklist(AccountingPeriod $period): void
    {
        ChecklistTask::query()->where('period_id', $period->id)->update(['status' => ChecklistStatus::Completed, 'completed_by' => $this->manager()->id]);
    }

    #[Test]
    public function every_period_carries_the_25_task_checklist(): void
    {
        $this->assertSame(25, ChecklistTask::query()->where('period_id', $this->period('2023-05')->id)->count());
        $this->assertSame(25, ChecklistTask::query()->where('period_id', $this->period('2026-12')->id)->count());
    }

    #[Test]
    #[Group('UAT-048')]
    public function an_incomplete_checklist_blocks_the_final_close(): void
    {
        $period = $this->period('2024-01');

        try {
            app(PeriodCloseService::class)->finalClose($this->manager(), $period);
            $this->fail('A period closed with open checklist tasks.');
        } catch (RuleViolation $v) {
            $this->assertStringContainsString('25', $v->getMessage());
        }

        $this->completeChecklist($period);
        $this->assertSame(PeriodStatus::FinalClose, app(PeriodCloseService::class)->finalClose($this->manager(), $period)->status);
    }

    #[Test]
    public function an_unposted_entry_blocks_the_final_close(): void
    {
        $period = $this->period('2026-03');
        $this->draft('TT-01', $this->fundingLines(1_000));
        $this->completeChecklist($period);

        $blockers = app(PeriodCloseService::class)->blockers($period);

        $this->assertNotEmpty(array_filter($blockers, fn (string $b): bool => str_contains($b, '1')));
    }

    #[Test]
    public function the_2026_periods_cannot_be_finally_closed_while_the_source_is_incomplete(): void
    {
        $period = $this->period('2026-01');
        $this->completeChecklist($period);

        $this->expectException(RuleViolation::class);
        $this->expectExceptionMessage('EXC-SYS-04');

        app(PeriodCloseService::class)->finalClose($this->manager(), $period);
    }

    #[Test]
    #[Group('UAT-007')]
    #[Group('UAT-008')]
    public function a_closed_period_refuses_postings_until_the_finance_manager_reopens_it_with_a_reason(): void
    {
        $period = $this->period('2025-06');
        $this->completeChecklist($period);
        app(PeriodCloseService::class)->finalClose($this->manager(), $period);

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-01', $this->fundingLines(1_000), ['posting_date' => '2025-06-10', 'txn_date' => '2025-06-10']));
            $this->fail('A posting went into a final-closed period.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-09'));
        }

        try {
            app(PeriodCloseService::class)->reopen($this->reviewer(), $period, 'Late invoice');
            $this->fail('A Senior Accountant reopened a period.');
        } catch (AuthorizationException) {
        }

        $reopened = app(PeriodCloseService::class)->reopen($this->manager(), $period, 'Late supplier invoice approved by the board');

        $this->assertSame(PeriodStatus::Open, $reopened->status);
        $this->assertTrue(DB::table('audit_log')->where('action', 'period_reopen')->where('reason', 'Late supplier invoice approved by the board')->exists());
    }

    #[Test]
    public function the_senior_accountant_may_soft_close_but_not_final_close(): void
    {
        $period = $this->period('2025-07');

        $this->assertSame(PeriodStatus::SoftClose, app(PeriodCloseService::class)->softClose($this->reviewer(), $period)->status);

        $this->expectException(AuthorizationException::class);
        app(PeriodCloseService::class)->finalClose($this->reviewer(), $period->fresh());
    }

    #[Test]
    public function a_locked_period_cannot_be_reopened_by_the_application(): void
    {
        $period = $this->period('2025-08');
        $this->completeChecklist($period);
        app(PeriodCloseService::class)->finalClose($this->manager(), $period);
        $locked = app(PeriodCloseService::class)->lock($this->manager(), $period);

        $this->assertSame(PeriodStatus::Locked, $locked->status);

        $this->expectException(RuleViolation::class);
        app(PeriodCloseService::class)->reopen($this->manager(), $locked, 'Try');
    }

    #[Test]
    #[Group('VR-53')]
    public function the_year_end_transfer_waits_for_the_final_close_of_the_last_period(): void
    {
        $lines = [
            $this->line('310040', debit: 1_000),
            $this->line('310030', credit: 1_000),
        ];
        $header = ['posting_date' => '2025-12-31', 'txn_date' => '2025-12-31', 'approval_ref' => 'AGM-2025'];

        $early = $this->draft('TT-38', $lines, $header);

        try {
            app(JournalWorkflow::class)->submit($this->maker(), $early);
            $this->fail('The year-end transfer went in before the final close.');
        } catch (JournalRejected $e) {
            $this->assertTrue($e->has('VR-53'));
        }

        // An unposted draft would itself block the close.
        app(JournalService::class)->deleteDraft($this->maker(), $early);

        $december = $this->period('2025-12');
        $this->completeChecklist($december);
        app(PeriodCloseService::class)->finalClose($this->manager(), $december);

        $posted = $this->postEntry('TT-38', $lines, $header);
        $this->assertNotNull($posted->jv_no);
    }

    #[Test]
    public function only_an_administrator_at_the_database_may_unlock_a_period(): void
    {
        $this->assertFalse((bool) DB::scalar("SELECT has_function_privilege('public', 'admin_unlock_period(bigint, text)', 'EXECUTE')"));
    }
}
