<?php

declare(strict_types=1);

namespace Tests\Feature\Controls;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Controls\Exceptions\ExceptionLifecycle;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Shared\Documents\DocumentEvidenceService;
use App\Domain\Shared\Exceptions\RuleViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M09: the exceptions register (Document B §4.7). */
class ExceptionsTest extends TestCase
{
    use BuildsJournals;

    /** @return array<string, mixed> */
    private function custody(): array
    {
        return ['advance_holder_id' => $this->counterpartyId('P-02'), 'counterparty_id' => $this->counterpartyId('P-02'), 'resp_center_id' => $this->rcId('RC-02')];
    }

    private function shortfall(): ControlException
    {
        $this->postEntry('TT-01', $this->fundingLines(100_000_000));
        $this->postEntry('TT-07', [
            $this->line('112035', debit: 100_000_000, extra: $this->custody() + ['settlement_deadline' => '2026-06-30']),
            $this->line('111002', credit: 100_000_000, extra: ['cash_account_id' => $this->cashId(), 'resp_center_id' => $this->rcId('RC-02')]),
        ]);

        $this->postEntry('TT-32', [
            $this->line('112090', debit: 3_803_500, extra: ['resp_center_id' => $this->rcId('RC-02')]),
            $this->line('112035', credit: 3_803_500, extra: $this->custody()),
        ]);

        return ControlException::query()->where('category', ExceptionCategory::SuspenseItem)->latest('id')->firstOrFail();
    }

    #[Test]
    #[Group('UAT-037')]
    #[Group('VR-47')]
    public function a_shortfall_to_suspense_opens_an_exception_with_an_owner(): void
    {
        $exception = $this->shortfall();

        $this->assertSame(ExceptionStatus::Open, $exception->status);
        $this->assertSame(3_803_500, $exception->amount);
        $this->assertNotNull($exception->owner_id, 'VR-47: a responsible user.');
    }

    #[Test]
    #[Group('UAT-038')]
    #[Group('VR-48')]
    public function clearing_suspense_needs_a_resolution_and_closes_the_exception(): void
    {
        $exception = $this->shortfall();

        $this->postEntry('TT-33', [
            $this->line('112102', debit: 3_803_500, extra: ['counterparty_id' => $this->counterpartyId('P-02')]),
            $this->line('112090', credit: 3_803_500),
        ], ['resolution_ref' => $exception->exception_no, 'approval_ref' => 'MGMT-2026-3']);

        $this->assertSame(ExceptionStatus::Resolved, $exception->fresh()->status);
        $this->assertNotNull($exception->fresh()->resolution);
    }

    #[Test]
    #[Group('UAT-041')]
    public function a_missing_document_stays_visible_until_it_arrives(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000), ['doc_status' => 'missing', 'doc_ref' => null], ['VR-13']);

        $exception = ControlException::query()->where('journal_header_id', $posted->id)->firstOrFail();
        $this->assertSame(ExceptionCategory::MissingDocument, $exception->category);

        app(DocumentEvidenceService::class)->record($this->maker(), $posted, DocStatus::Complete, 'BANK-ADV-77');

        $this->assertSame(DocStatus::Complete, $posted->fresh()->doc_status);
        $this->assertSame(ExceptionStatus::Resolved, $exception->fresh()->status);
    }

    #[Test]
    #[Group('VR-59')]
    public function an_exception_is_resolved_only_with_a_resolution_and_never_deleted(): void
    {
        $exception = ControlException::query()->where('source_code', 'EXC-SYS-01')->firstOrFail();

        try {
            app(ExceptionLifecycle::class)->resolve($this->manager(), $exception, '  ');
            $this->fail('An exception was resolved without a resolution.');
        } catch (RuleViolation $violation) {
            $this->assertSame('VR-59', $violation->rule);
        }

        $this->expectException(QueryException::class);
        DB::table('exceptions')->where('id', $exception->id)->delete();
    }

    #[Test]
    public function the_register_carries_the_standalone_system_exceptions(): void
    {
        $this->assertSame(
            ['EXC-SYS-01', 'EXC-SYS-02', 'EXC-SYS-04', 'EXC-SYS-05'],
            ControlException::query()->whereNotNull('source_code')->orderBy('source_code')->pluck('source_code')->all(),
        );
        $this->assertTrue(ControlException::query()->where('source_code', 'EXC-SYS-04')->value('blocks_final_close'));
    }

    #[Test]
    public function the_internal_auditor_comments_but_cannot_resolve(): void
    {
        $auditor = $this->userWithRole('internal_auditor');
        $exception = ControlException::query()->where('source_code', 'EXC-SYS-05')->firstOrFail();

        app(ExceptionLifecycle::class)->comment($auditor, $exception, 'Seen in the contract file review.');
        $this->assertCount(1, $exception->comments);

        $this->expectException(AuthorizationException::class);
        app(ExceptionLifecycle::class)->resolve($auditor, $exception, 'Closing it');
    }
}
