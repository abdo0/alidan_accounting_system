<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalService;
use App\Domain\Ledger\Posting\ReversalService;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\Shared\Approval;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M03, Document B §4.2: the Draft -> Posted state machine and maker-checker. */
class WorkflowTest extends TestCase
{
    use BuildsJournals;

    #[Test]
    #[Group('UAT-001')]
    #[Group('UAT-012')]
    public function a_balanced_entry_walks_draft_to_posted(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(250_000_000));

        $this->assertSame(JournalStatus::Posted, $posted->status);
        $this->assertMatchesRegularExpression('/^JV-2026-\d{5}$/', (string) $posted->jv_no);
        $this->assertSame('PR-01', $posted->postingRule?->code);
        $this->assertNotNull($posted->entry_hash);

        $this->assertSame(
            ['submit', 'review', 'approve', 'post'],
            Approval::query()->where('object_type', 'journal_headers')->where('object_id', $posted->id)->orderBy('id')->get()->map(fn (Approval $a): string => $a->action->value)->all(),
            'Every transition writes an approvals row.',
        );
    }

    #[Test]
    #[Group('UAT-009')]
    #[Group('VR-16')]
    public function nobody_reviews_or_approves_what_they_created(): void
    {
        $senior = $this->reviewer();
        $journal = $this->draft('TT-01', $this->fundingLines(1_000), maker: $senior);
        $journal = app(JournalWorkflow::class)->submit($senior, $journal);

        try {
            app(JournalWorkflow::class)->review($senior, $journal);
            $this->fail('A Senior Accountant reviewed their own entry.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-16'));
        }

        $journal = app(JournalWorkflow::class)->review($this->userWithRole('senior_accountant'), $journal);

        $this->expectException(AuthorizationException::class);
        app(JournalWorkflow::class)->approve($senior, $journal);
    }

    #[Test]
    #[Group('VR-17')]
    public function only_the_finance_manager_posts_and_only_an_approved_entry(): void
    {
        $journal = app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-01', $this->fundingLines(1_000)));

        try {
            app(JournalWorkflow::class)->post($this->manager(), $journal);
            $this->fail('A submitted entry was posted.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-17'));
        }

        $journal = app(JournalWorkflow::class)->approve($this->manager(), app(JournalWorkflow::class)->review($this->reviewer(), $journal));

        try {
            app(JournalWorkflow::class)->post($this->reviewer(), $journal);
            $this->fail('A Senior Accountant posted.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-17'));
        }

        $this->assertTrue(
            DB::table('audit_log')->where('action', 'journal_post_rejected')->where('record_id', $journal->id)->exists(),
            'A refused posting leaves a rejected-attempt audit row.',
        );
    }

    #[Test]
    public function rejection_returns_the_entry_to_draft_with_a_comment(): void
    {
        $journal = app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-01', $this->fundingLines(1_000)));

        $journal = app(JournalWorkflow::class)->reject($this->reviewer(), $journal, 'Wrong project');

        $this->assertSame(JournalStatus::Draft, $journal->status);
        $this->assertSame('Wrong project', $journal->rejection_comment);
        $this->assertNull($journal->submitted_by);
    }

    #[Test]
    #[Group('VR-10')]
    public function journal_numbers_are_gapless_and_drawn_only_at_posting(): void
    {
        $first = $this->postEntry('TT-01', $this->fundingLines(1_000));
        $abandoned = $this->draft('TT-01', $this->fundingLines(2_000));
        $second = $this->postEntry('TT-01', $this->fundingLines(3_000));

        $this->assertNull($abandoned->jv_no);
        $this->assertSame((int) substr((string) $first->jv_no, -5) + 1, (int) substr((string) $second->jv_no, -5));
    }

    #[Test]
    #[Group('UAT-010')]
    #[Group('VR-18')]
    public function a_posted_entry_cannot_be_edited_through_the_service(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000));

        $this->expectException(JournalRejected::class);

        app(JournalService::class)->saveDraft($this->manager(), ['posting_date' => '2026-03-15', 'description_ar' => 'x'], $this->fundingLines(5), $posted);
    }

    #[Test]
    #[Group('UAT-011')]
    #[Group('VR-51')]
    public function a_reversal_mirrors_the_entry_and_both_stay_visible(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(100_000_000));

        $mirror = app(ReversalService::class)->reverse($this->manager(), $posted, 'Posted to the wrong shareholder', CarbonImmutable::parse('2026-03-20'));

        $this->assertSame(JournalStatus::Reversed, $posted->fresh()->status);
        $this->assertSame($mirror->id, $posted->fresh()->reversed_by_journal_id);
        $this->assertSame($posted->id, $mirror->reversal_of_journal_id);
        $this->assertSame(JournalStatus::Posted, $mirror->status);

        $original = $posted->lines->keyBy('account_id');
        foreach ($mirror->lines as $line) {
            $this->assertSame($original[$line->account_id]->debit, $line->credit);
            $this->assertSame($original[$line->account_id]->credit, $line->debit);
        }

        $net = DB::table('journal_lines')->whereIn('journal_header_id', [$posted->id, $mirror->id])->selectRaw('sum(debit) - sum(credit) AS n')->value('n');
        $this->assertSame(0, (int) $net);
        $this->assertSame(2, JournalHeader::query()->inLedger()->whereIn('id', [$posted->id, $mirror->id])->count());
    }

    #[Test]
    #[Group('VR-51')]
    public function a_reversal_needs_a_reason_and_cannot_be_repeated(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000));

        try {
            app(ReversalService::class)->reverse($this->manager(), $posted, ' ');
            $this->fail('A reversal without a reason was accepted.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-51'));
        }

        app(ReversalService::class)->reverse($this->manager(), $posted, 'reason', CarbonImmutable::parse('2026-03-20'));

        $this->expectException(JournalRejected::class);
        app(ReversalService::class)->reverse($this->manager(), $posted->fresh(), 'again', CarbonImmutable::parse('2026-03-20'));
    }

    #[Test]
    public function data_entry_cannot_edit_someone_elses_draft(): void
    {
        $journal = $this->draft('TT-01', $this->fundingLines(1_000));

        $this->expectException(AuthorizationException::class);

        app(JournalService::class)->saveDraft($this->userWithRole('data_entry'), ['posting_date' => '2026-03-15', 'description_ar' => 'x'], $this->fundingLines(5), $journal);
    }
}
