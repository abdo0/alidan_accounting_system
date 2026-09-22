<?php

declare(strict_types=1);

namespace Tests\Feature\Controls;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Duplicates\DispositionService;
use App\Domain\Controls\Enums\DuplicateDisposition;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\Shared\Exceptions\RuleViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** Document B §2.5, VR-30. */
#[Group('VR-30')]
class DuplicateDetectionTest extends TestCase
{
    use BuildsJournals;

    #[Test]
    #[Group('UAT-039')]
    public function the_same_amount_counterparty_and_period_is_flagged_and_held_until_dispositioned(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(17_000_000));

        $second = $this->submitReviewApprove($this->draft('TT-01', $this->fundingLines(17_000_000)));

        $flags = DuplicateFlag::query()->where('journal_header_id', $second->id)->get();
        $this->assertContains(DuplicateFlagType::Exact, $flags->pluck('flag_type')->all());
        $this->assertSame(17_000_000, $flags->first()->value_at_risk);

        try {
            app(JournalWorkflow::class)->post($this->manager(), $second);
            $this->fail('A flagged entry was posted before its disposition.');
        } catch (JournalRejected $rejected) {
            $this->assertTrue($rejected->has('VR-30'));
        }

        foreach ($flags as $flag) {
            app(DispositionService::class)->disposition($this->reviewer(), $flag, DuplicateDisposition::NotADuplicate, 'Second tranche, confirmed with the bank');
        }

        $this->assertSame(JournalStatus::Posted, app(JournalWorkflow::class)->post($this->manager(), $second->fresh())->status);
    }

    #[Test]
    #[Group('UAT-040')]
    public function a_source_reference_already_posted_is_flagged(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(1_000), ['source_reference' => 'DF-12']);

        $draft = $this->draft('TT-01', $this->fundingLines(9_999), ['source_reference' => 'DF-12']);

        $this->assertTrue(DuplicateFlag::query()->where('journal_header_id', $draft->id)->where('flag_type', DuplicateFlagType::SourceReference)->exists());
    }

    #[Test]
    public function a_confirmed_duplicate_can_never_be_posted(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(5_000));
        $second = $this->submitReviewApprove($this->draft('TT-01', $this->fundingLines(5_000)));

        foreach (DuplicateFlag::query()->where('journal_header_id', $second->id)->get() as $flag) {
            app(DispositionService::class)->disposition($this->reviewer(), $flag, DuplicateDisposition::ConfirmedDuplicate, 'Same bank transfer');
        }

        $this->expectException(JournalRejected::class);
        app(JournalWorkflow::class)->post($this->manager(), $second);
    }

    #[Test]
    public function the_maker_cannot_disposition_their_own_flag(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(5_000));
        $second = $this->draft('TT-01', $this->fundingLines(5_000), maker: $this->reviewer());
        $flag = DuplicateFlag::query()->where('journal_header_id', $second->id)->firstOrFail();

        $this->expectException(RuleViolation::class);
        app(DispositionService::class)->disposition($this->reviewer(), $flag, DuplicateDisposition::NotADuplicate, 'mine');
    }

    #[Test]
    public function a_flag_is_never_deleted_and_a_disposition_never_withdrawn(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(5_000));
        $flag = DuplicateFlag::query()->where('journal_header_id', $this->draft('TT-01', $this->fundingLines(5_000))->id)->firstOrFail();
        app(DispositionService::class)->disposition($this->reviewer(), $flag, DuplicateDisposition::HeldUnposted, 'Awaiting bank');

        foreach ([
            fn () => DB::table('duplicate_flags')->where('id', $flag->id)->delete(),
            fn () => DB::table('duplicate_flags')->where('id', $flag->id)->update(['disposition' => null]),
        ] as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('A flag was deleted or its disposition withdrawn.');
            } catch (QueryException $e) {
                $this->assertMatchesRegularExpression('/never deleted|cannot be withdrawn/', $e->getMessage());
            }
        }
    }
}
