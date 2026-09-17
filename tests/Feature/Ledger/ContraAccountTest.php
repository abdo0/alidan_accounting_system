<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\Account;
use App\Domain\Ledger\JournalEntry;
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

/**
 * الحسابات المتقابلة — classes 19 and 29.
 *
 * A paired memorandum mechanism for commitments (letters of credit, guarantees,
 * contracts), nominal-value assets, and the imputed figures that feed the Value Added
 * statement. Each leg mirrors its partner, so the pair nets to nothing and neither shows
 * inside the balance sheet totals.
 */
class ContraAccountTest extends TestCase
{
    private Entity $entity;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceSeeder::class);
        $this->entity = Entity::where('code', 'ALIDAN')->firstOrFail();
        $this->accountant = User::factory()->create();
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    /** @param  list<JournalLineDraft>  $lines */
    private function postEntry(array $lines): JournalEntry
    {
        return app(PostingService::class)->post(
            new JournalEntryDraft(
                entityId: $this->entity->id,
                journalCode: 'GJ',
                entryDate: CarbonImmutable::create(2026, 3, 15),
                description: 'Contra test',
                lines: $lines,
                sourceDocumentNo: 'DOC-MEMO',
            ),
            $this->accountant,
        );
    }

    #[Test]
    public function the_chart_links_every_contra_account_to_its_partner(): void
    {
        $memo = Account::where('statement', 'MEMO')->get();

        $this->assertCount(30, $memo, 'The UAS chart carries 30 memorandum accounts.');

        foreach ($memo as $account) {
            $this->assertNotNull(
                $account->contra_pair_code,
                "Account {$account->code} has no paired account."
            );

            $this->assertTrue(
                Account::where('code', $account->contra_pair_code)->exists(),
                "Partner {$account->contra_pair_code} of {$account->code} does not exist."
            );
        }
    }

    /**
     * Pairing is by trailing digits. 1922 carries the word مقابل and 2922 does not, which
     * is the opposite of 1921/2921 — so any rule keyed off مقابل would mispair these.
     */
    #[Test]
    public function pairing_follows_the_digits_not_the_word_muqabil(): void
    {
        $this->assertSame('2921', $this->account('1921')->contra_pair_code);
        $this->assertSame('2922', $this->account('1922')->contra_pair_code);

        $this->assertStringNotContainsString('مقابل', $this->account('1921')->name_ar);
        $this->assertStringContainsString('مقابل', $this->account('1922')->name_ar);
        $this->assertStringContainsString('مقابل', $this->account('2921')->name_ar);
        $this->assertStringNotContainsString('مقابل', $this->account('2922')->name_ar);
    }

    #[Test]
    public function a_matched_contra_pair_posts(): void
    {
        // A letter of credit received: recorded on both legs, netting to nothing.
        $entry = $this->postEntry([
            JournalLineDraft::debit($this->account('1921')->id, '25000000'),
            JournalLineDraft::credit($this->account('2921')->id, '25000000'),
        ]);

        $this->assertSame(JournalEntry::POSTED, $entry->status);
        $this->assertCount(2, $entry->lines);
    }

    #[Test]
    public function v19_rejects_a_pair_whose_trailing_digits_disagree(): void
    {
        try {
            $this->postEntry([
                JournalLineDraft::debit($this->account('1921')->id, '25000000'),
                // 2923 is the partner of 1923, not of 1921.
                JournalLineDraft::credit($this->account('2923')->id, '25000000'),
            ]);
            $this->fail('A mismatched contra pair must not post.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-19'));
        }
    }

    #[Test]
    public function v19_rejects_mixing_memorandum_and_financial_accounts(): void
    {
        try {
            $this->postEntry([
                JournalLineDraft::debit($this->account('1921')->id, '1000000'),
                JournalLineDraft::credit($this->account('183')->id, '1000000'),
            ]);
            $this->fail('A memorandum entry may not touch a financial account.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-19'));
        }
    }

    #[Test]
    public function v19_rejects_a_pair_of_unequal_amounts(): void
    {
        try {
            $this->postEntry([
                JournalLineDraft::debit($this->account('1921')->id, '25000000'),
                JournalLineDraft::debit($this->account('1923')->id, '5000000'),
                JournalLineDraft::credit($this->account('2921')->id, '30000000'),
            ]);
            $this->fail('Each leg must be mirrored for the same amount.');
        } catch (PostingException $e) {
            $this->assertTrue($e->failed('V-19'));
        }
    }

    #[Test]
    public function several_pairs_may_share_one_entry(): void
    {
        $entry = $this->postEntry([
            JournalLineDraft::debit($this->account('1921')->id, '10000000'),
            JournalLineDraft::credit($this->account('2921')->id, '10000000'),
            JournalLineDraft::debit($this->account('1923')->id, '4000000'),
            JournalLineDraft::credit($this->account('2923')->id, '4000000'),
        ]);

        $this->assertSame(JournalEntry::POSTED, $entry->status);
        $this->assertCount(4, $entry->lines);
    }

    /**
     * The point of the mechanism: exposure is tracked without touching the balance
     * sheet. A report that sums class 1 and class 2 must exclude MEMO.
     */
    #[Test]
    public function contra_balances_sit_outside_the_balance_sheet_totals(): void
    {
        $this->postEntry([
            JournalLineDraft::debit($this->account('1921')->id, '25000000'),
            JournalLineDraft::credit($this->account('2921')->id, '25000000'),
        ]);

        $financial = DB::table('gl_balances')
            ->join('accounts', 'accounts.id', '=', 'gl_balances.account_id')
            ->whereIn('accounts.statement', ['BS'])
            ->sum(DB::raw('period_debit - period_credit'));

        $memo = DB::table('gl_balances')
            ->join('accounts', 'accounts.id', '=', 'gl_balances.account_id')
            ->where('accounts.statement', 'MEMO')
            ->sum(DB::raw('period_debit - period_credit'));

        $this->assertSame(0.0, (float) $financial, 'The balance sheet must be untouched.');
        // The pair itself nets to nil, which is what "لا يظهر لهما رصيد في الميزانية" means.
        $this->assertSame(0.0, (float) $memo);
    }
}
