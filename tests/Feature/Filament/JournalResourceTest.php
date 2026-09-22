<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\TransactionType;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Resources\Journal\JournalHeaderResource\Pages\CreateJournalHeader;
use App\Filament\Resources\Journal\JournalHeaderResource\Pages\CreateSimpleJournal;
use App\Filament\Resources\Journal\JournalHeaderResource\Pages\ViewJournalHeader;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

class JournalResourceTest extends TestCase
{
    use BuildsJournals;

    #[Test]
    public function the_multi_line_form_saves_a_draft(): void
    {
        $this->actingAs($this->maker());
        $undo = Repeater::fake();

        Livewire::test(CreateJournalHeader::class)
            ->fillForm([
                'transaction_type_id' => TransactionType::query()->where('code', 'TT-01')->value('id'),
                'posting_date' => '2026-03-15',
                'txn_date' => '2026-03-15',
                'date_status' => 'ok',
                'description_ar' => 'تمويل من المساهم',
                'doc_status' => 'complete',
                'doc_ref' => 'BANK-ADV-1',
                'lines' => $this->fundingLines(9_000_000),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undo();

        $journal = JournalHeader::query()->where('description_ar', 'تمويل من المساهم')->firstOrFail();
        $this->assertSame(JournalStatus::Draft, $journal->status);
        $this->assertSame(9_000_000, $journal->totalDebit());
    }

    #[Test]
    #[Group('REQ-009')]
    public function the_simple_screen_writes_a_header_with_two_lines(): void
    {
        $this->actingAs($this->maker());

        Livewire::test(CreateSimpleJournal::class)
            ->fillForm([
                'transaction_type_id' => TransactionType::query()->where('code', 'TT-06')->value('id'),
                'posting_date' => '2026-03-15',
                'txn_date' => '2026-03-15',
                'date_status' => 'ok',
                'description_ar' => 'مناقلة',
                'doc_status' => 'missing',
                'debit_account_id' => $this->accountId('111010'),
                'credit_account_id' => $this->accountId('111002'),
                'amount' => 5_000,
                'project_id' => $this->projectId(),
                'cost_center_id' => $this->costCenterId(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $journal = JournalHeader::query()->where('description_ar', 'مناقلة')->with('lines')->firstOrFail();
        $this->assertCount(2, $journal->lines);
        $this->assertSame(5_000, $journal->lines[0]->debit);
        $this->assertSame(5_000, $journal->lines[1]->credit);
    }

    #[Test]
    public function the_view_page_offers_each_role_only_its_own_step(): void
    {
        $journal = app(JournalWorkflow::class)->submit($this->maker(), $this->draft('TT-01', $this->fundingLines(1_000)));

        $this->actingAs($this->reviewer());
        Livewire::test(ViewJournalHeader::class, ['record' => $journal->getRouteKey()])
            ->assertActionVisible('review')
            ->assertActionHidden('approve')
            ->assertActionHidden('post');

        $this->actingAs($this->manager());
        Livewire::test(ViewJournalHeader::class, ['record' => $journal->getRouteKey()])
            ->assertActionHidden('post')
            ->assertActionVisible('reject');
    }

    #[Test]
    public function the_finance_manager_posts_from_the_view_page(): void
    {
        $journal = $this->submitReviewApprove($this->draft('TT-01', $this->fundingLines(2_000)));

        $this->actingAs($this->manager());
        Livewire::test(ViewJournalHeader::class, ['record' => $journal->getRouteKey()])
            ->callAction('post');

        $this->assertSame(JournalStatus::Posted, $journal->fresh()->status);
    }

    #[Test]
    public function the_journal_pages_render(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(3_000));
        $this->actingAs($this->manager());

        $this->get(JournalHeaderResource::getUrl('index'))->assertSuccessful();
        $this->get(JournalHeaderResource::getUrl('view', ['record' => $posted]))->assertSuccessful()->assertSee((string) $posted->jv_no);
        $this->get(JournalHeaderResource::getUrl('simple'))->assertSuccessful();
    }
}
