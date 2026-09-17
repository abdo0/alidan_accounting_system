<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Ledger\Account;
use App\Domain\Ledger\JournalEntry;
use App\Domain\Ledger\Posting\EntryHasher;
use App\Domain\Ledger\Posting\JournalEntryDraft;
use App\Domain\Ledger\Posting\JournalLineDraft;
use App\Domain\Ledger\Posting\PostingException;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Organisation\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostingEngineTest extends TestCase
{
    // Accounts and cost centres below are Iraqi Unified Accounting System codes:
    //   3352 استئجار مباني وإنشاءات  (a use; requires a cost centre)
    //   183  نقدية لدى المصارف       (class 18 النقود, so adjusting entries may not touch it)
    //   1611 مدينون قطاع عام          (inside the 161 receivables control branch)
    //   1621 أوراق قبض قطاع عام        (balance sheet asset, outside any control branch)
    //   2663 مصاريف مستحقة            (balance sheet liability)
    //   33   المستلزمات الخدمية       (a heading -- never postable)
    // Cost centres 51 production, 61 production-service, 71 marketing, 5 heading.
    private Entity $entity;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceSeeder::class);
        $this->entity = Entity::where('code', 'ALIDAN')->firstOrFail();
        $this->accountant = User::factory()->create(['name' => 'GL Accountant']);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    private function costCentre(string $code): CostCentre
    {
        return CostCentre::where('code', $code)->firstOrFail();
    }

    /**
     * @param  list<JournalLineDraft>  $lines
     * @param  array<string, mixed>  $overrides
     */
    private function draft(array $lines, array $overrides = []): JournalEntryDraft
    {
        return new JournalEntryDraft(
            entityId: $this->entity->id,
            journalCode: $overrides['journalCode'] ?? 'GJ',
            entryDate: $overrides['entryDate'] ?? CarbonImmutable::create(2026, 3, 15),
            description: $overrides['description'] ?? 'Test entry',
            lines: $lines,
            sourceType: $overrides['sourceType'] ?? 'manual',
            sourceDocumentNo: array_key_exists('sourceDocumentNo', $overrides)
                ? $overrides['sourceDocumentNo']
                : 'DOC-001',
            isAdjusting: $overrides['isAdjusting'] ?? false,
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
        );
    }

    private function postEntry(JournalEntryDraft $draft): JournalEntry
    {
        return app(PostingService::class)->post($draft, $this->accountant);
    }

    // ---------------------------------------------------------------- happy path

    #[Test]
    public function it_posts_a_balanced_entry(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '3000000', $this->costCentre('51')->id, 'إيجار آذار'),
            JournalLineDraft::credit($this->account('183')->id, '3000000'),
        ]));

        $this->assertSame(JournalEntry::POSTED, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertSame('3000000.0000', (string) $entry->total_debit);
        $this->assertSame('3000000.0000', (string) $entry->total_credit);
        $this->assertCount(2, $entry->lines);
    }

    #[Test]
    public function numbering_is_gapless_and_prefixed(): void
    {
        $numbers = [];

        for ($i = 0; $i < 3; $i++) {
            $numbers[] = $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ]))->entry_no;
        }

        $this->assertSame(['GJ-00001', 'GJ-00002', 'GJ-00003'], $numbers);
    }

    #[Test]
    public function it_updates_the_balance_store(): void
    {
        $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '3000000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '3000000'),
        ]));

        $rent = DB::table('gl_balances')
            ->where('account_id', $this->account('3352')->id)
            ->where('cost_centre_id', $this->costCentre('51')->id)
            ->first();

        $this->assertNotNull($rent);
        $this->assertSame('3000000.0000', $rent->period_debit);
        // Functional columns must be written, not left at zero, or every report that
        // reads them silently returns nothing.
        $this->assertSame('3000000.0000', $rent->functional_period_debit);
    }

    /**
     * The hazard the balance updater exists for: several lines to the same account
     * and cost centre in one entry. Issued naively as a multi-row upsert this raises
     * SQLSTATE 21000, "ON CONFLICT DO UPDATE command cannot affect row a second time".
     */
    #[Test]
    public function it_aggregates_repeated_keys_within_one_entry(): void
    {
        $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000000', $this->costCentre('51')->id),
            JournalLineDraft::debit($this->account('3352')->id, '500000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1500000'),
        ]));

        $rows = DB::table('gl_balances')
            ->where('account_id', $this->account('3352')->id)
            ->where('cost_centre_id', $this->costCentre('51')->id)
            ->get();

        $this->assertCount(1, $rows, 'Repeated keys must accumulate into one balance row.');
        $this->assertSame('1500000.0000', $rows->first()->period_debit);
    }

    /**
     * NULLS NOT DISTINCT. Under PostgreSQL's default, a NULL cost centre would insert
     * a new row per posting instead of accumulating, because NULL <> NULL.
     */
    #[Test]
    public function balances_accumulate_when_the_cost_centre_is_null(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('1621')->id, '100000'),
                JournalLineDraft::credit($this->account('183')->id, '100000'),
            ]));
        }

        $rows = DB::table('gl_balances')
            ->where('account_id', $this->account('1621')->id)
            ->whereNull('cost_centre_id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('300000.0000', $rows->first()->period_debit);
    }

    #[Test]
    public function it_chains_the_tamper_evidence_hash(): void
    {
        $first = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        $second = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '2000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '2000'),
        ]));

        $this->assertSame(str_repeat('0', 64), $first->prev_entry_hash);
        $this->assertNotNull($first->entry_hash);
        $this->assertSame($first->entry_hash, $second->prev_entry_hash);
        $this->assertTrue(app(EntryHasher::class)->verify($second));
    }

    #[Test]
    public function the_same_idempotency_key_posts_only_once(): void
    {
        $lines = [
            JournalLineDraft::debit($this->account('3352')->id, '5000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '5000'),
        ];

        $first = $this->postEntry($this->draft($lines, ['idempotencyKey' => 'abc-123']));
        $second = $this->postEntry($this->draft($lines, ['idempotencyKey' => 'abc-123']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::where('status', JournalEntry::POSTED)->count());
    }

    // ------------------------------------------------------------- the rules bite

    #[Test]
    public function v01_it_rejects_an_unbalanced_entry(): void
    {
        $this->expectException(PostingException::class);

        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '900'),
            ]));
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-01'));

            throw $e;
        }
    }

    #[Test]
    public function v02_it_rejects_a_single_line_entry(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            ]));
            $this->fail('A one-sided entry must not post.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-02'));
        }
    }

    #[Test]
    public function v03_it_rejects_a_posting_into_a_closed_period(): void
    {
        DB::table('fiscal_periods')
            ->where('entity_id', $this->entity->id)
            ->whereDate('starts_on', '2026-03-01')
            ->update(['status' => 'closed']);

        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ]));
            $this->fail('A closed period must not accept postings.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-03'));
        }
    }

    #[Test]
    public function v05_it_rejects_a_heading_account(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('33')->id, '1000'),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ]));
            $this->fail('A heading account must not accept postings.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-05'));
        }
    }

    #[Test]
    public function v06_it_rejects_a_manual_posting_to_a_control_account(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('1611')->id, '1000'),
                JournalLineDraft::credit($this->account('4111')->id, '1000', $this->costCentre('71')->id),
            ]));
            $this->fail('A control account must be written only by its subledger.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-06'));
        }
    }

    #[Test]
    public function v07_it_requires_a_cost_centre_where_the_account_demands_one(): void
    {
        // 6500 Rent requires a cost centre and no default is configured anywhere,
        // so the cascade resolves to null and validation must stop it.
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000'),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ]));
            $this->fail('A cost-centre-required account must not post without one.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-07'));
        }
    }

    /**
     * The standard's own exception. كشف توزيع الإستخدامات على مراقبات مراكز التكاليف
     * (printed 417) allocates elements 31-39 across the five centre groups but records
     * of element 35: "لا يوزع (٣٥) على المراقبات مما يستوجب إضافته لدى إجراء المطابقة
     * لإجمالي عناصر الإستخدامات" -- it is not distributed, and must be added back when
     * reconciling total uses. A blanket cost-centre rule would reject this.
     */
    #[Test]
    public function v07_element_35_posts_without_a_cost_centre(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3511')->id, '5000000'),
            JournalLineDraft::credit($this->account('183')->id, '5000000'),
        ]));

        $this->assertSame(JournalEntry::POSTED, $entry->status);
        $this->assertNull(
            $entry->lines->firstWhere('account_id', $this->account('3511')->id)->cost_centre_id,
            'Element 35 is never allocated to a cost centre.'
        );
    }

    #[Test]
    public function v08_it_rejects_a_non_postable_cost_centre(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('5')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ]));
            $this->fail('A heading cost centre must not accept postings.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-08'));
        }
    }

    #[Test]
    public function v12_an_adjusting_entry_may_not_touch_cash(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ], ['isAdjusting' => true]));
            $this->fail('Doc A: adjusting entries never involve the cash account.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-12'));
        }
    }

    #[Test]
    public function v12_an_adjusting_entry_needs_both_statements(): void
    {
        try {
            // Two balance sheet accounts only.
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('1621')->id, '1000'),
                JournalLineDraft::credit($this->account('2663')->id, '1000'),
            ], ['isAdjusting' => true]));
            $this->fail('An adjusting entry must touch both statements.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-12'));
        }
    }

    #[Test]
    public function v12_a_valid_adjusting_entry_posts(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '400000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('1621')->id, '400000'),
        ], ['isAdjusting' => true]));

        $this->assertSame(JournalEntry::POSTED, $entry->status);
        $this->assertTrue($entry->is_adjusting);
    }

    #[Test]
    public function v13_a_manual_entry_needs_a_source_document_reference(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ], ['sourceDocumentNo' => null]));
            $this->fail('Objectivity: a manual entry needs documentary evidence.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-13'));
        }
    }

    #[Test]
    public function v15_a_manual_entry_cannot_use_a_system_journal(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ], ['journalCode' => 'ALC']));
            $this->fail('The allocation journal is written by the allocation run only.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-15'));
        }
    }

    #[Test]
    public function it_reports_every_broken_rule_at_once(): void
    {
        try {
            $this->postEntry($this->draft([
                JournalLineDraft::debit($this->account('3352')->id, '1000'),
                JournalLineDraft::credit($this->account('183')->id, '900'),
            ]));
            $this->fail('Expected a posting exception.');
        } catch (PostingException $e) {
            // Unbalanced AND missing a required cost centre; a user fixing this should
            // see both, not be led through one at a time.
            $this->assertTrue($e->failed('V-01'));
            $this->assertTrue($e->failed('V-07'));
        }
    }

    // ------------------------------------------------------------- immutability

    #[Test]
    public function a_posted_entry_cannot_be_updated(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        $this->expectExceptionMessageMatches('/posted and cannot be modified/');

        DB::table('journal_entries')->where('id', $entry->id)->update(['description' => 'tampered']);
    }

    #[Test]
    public function a_posted_line_cannot_be_updated(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        $this->expectExceptionMessageMatches('/posted entry and cannot be modified/');

        DB::table('journal_lines')
            ->where('journal_entry_id', $entry->id)
            ->update(['debit_amount' => 999999]);
    }

    #[Test]
    public function a_posted_line_cannot_be_deleted(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        DB::table('journal_lines')->where('journal_entry_id', $entry->id)->delete();

        $this->assertSame(2, DB::table('journal_lines')->where('journal_entry_id', $entry->id)->count());
    }

    #[Test]
    public function a_posted_entry_cannot_be_deleted(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000', $this->costCentre('51')->id),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        DB::table('journal_entries')->where('id', $entry->id)->delete();

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    // ------------------------------------------------------------- dimensions

    #[Test]
    public function the_header_cost_centre_defaults_onto_the_lines(): void
    {
        $draft = new JournalEntryDraft(
            entityId: $this->entity->id,
            journalCode: 'GJ',
            entryDate: CarbonImmutable::create(2026, 3, 15),
            description: 'Header default',
            lines: [
                JournalLineDraft::debit($this->account('3352')->id, '1000'),
                JournalLineDraft::credit($this->account('183')->id, '1000'),
            ],
            sourceDocumentNo: 'DOC-002',
            costCentreId: $this->costCentre('61')->id,
        );

        $entry = $this->postEntry($draft);

        $this->assertSame(
            $this->costCentre('61')->id,
            $entry->lines->firstWhere('account_id', $this->account('3352')->id)->cost_centre_id
        );
    }

    #[Test]
    public function the_account_default_cost_centre_applies(): void
    {
        $this->account('3352')->update(['default_cost_centre_id' => $this->costCentre('71')->id]);

        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1000'),
            JournalLineDraft::credit($this->account('183')->id, '1000'),
        ]));

        $this->assertSame(
            $this->costCentre('71')->id,
            $entry->lines->firstWhere('account_id', $this->account('3352')->id)->cost_centre_id
        );
    }

    #[Test]
    public function a_cost_split_posts_as_separate_lines_and_stays_balanced(): void
    {
        $entry = $this->postEntry($this->draft([
            JournalLineDraft::debit($this->account('3352')->id, '1500000', $this->costCentre('51')->id),
            JournalLineDraft::debit($this->account('3352')->id, '900000', $this->costCentre('61')->id),
            JournalLineDraft::debit($this->account('3352')->id, '600000', $this->costCentre('71')->id),
            JournalLineDraft::credit($this->account('183')->id, '3000000'),
        ]));

        $this->assertCount(4, $entry->lines);
        $this->assertSame('3000000.0000', (string) $entry->total_debit);

        $byCentre = DB::table('gl_balances')
            ->where('account_id', $this->account('3352')->id)
            ->pluck('period_debit', 'cost_centre_id');

        $this->assertSame('1500000.0000', $byCentre[$this->costCentre('51')->id]);
        $this->assertSame('900000.0000', $byCentre[$this->costCentre('61')->id]);
        $this->assertSame('600000.0000', $byCentre[$this->costCentre('71')->id]);
    }
}
