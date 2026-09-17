<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Ledger\Account;
use App\Domain\Ledger\Posting\JournalEntryDraft;
use App\Domain\Ledger\Posting\JournalLineDraft;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Organisation\Entity;
use App\Domain\Organisation\FiscalYear;
use App\Domain\Reporting\RenderedStatement;
use App\Domain\Reporting\StatementDefinition;
use App\Domain\Reporting\StatementPdf;
use App\Domain\Reporting\StatementRenderer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The prescribed statements of الفصل الرابع, resolved against real postings.
 */
class StatementTest extends TestCase
{
    private Entity $entity;

    private FiscalYear $year;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceSeeder::class);
        $this->entity = Entity::where('code', 'ALIDAN')->firstOrFail();
        $this->year = FiscalYear::where('code', 'FY2026')->firstOrFail();
        $this->accountant = User::factory()->create();
    }

    private function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    private function centre(string $code): int
    {
        return CostCentre::where('code', $code)->firstOrFail()->id;
    }

    /** @param  list<JournalLineDraft>  $lines */
    private function postEntry(array $lines, string $date = '2026-03-15'): void
    {
        app(PostingService::class)->post(
            new JournalEntryDraft(
                entityId: $this->entity->id,
                journalCode: 'GJ',
                entryDate: CarbonImmutable::parse($date),
                description: 'Statement fixture',
                lines: $lines,
                sourceDocumentNo: 'DOC-STMT',
            ),
            $this->accountant,
        );
    }

    private function render(string $code): RenderedStatement
    {
        return app(StatementRenderer::class)->render(
            StatementDefinition::where('code', $code)->firstOrFail(),
            $this->entity->id,
            $this->year,
        );
    }

    /**
     * Builds a small but complete set of trading facts: revenue, intermediate inputs,
     * wages, depreciation, indirect tax and a subsidy — one of each component the value
     * added computation touches.
     */
    private function seedTrading(): void
    {
        // Revenue 20,000,000 against the bank.
        $this->postEntry([
            JournalLineDraft::debit($this->account('183'), '20000000'),
            JournalLineDraft::credit($this->account('4111'), '20000000'),
        ]);

        // Intermediate inputs: service requisites 4,000,000 to a production centre.
        $this->postEntry([
            JournalLineDraft::debit($this->account('3352'), '4000000', $this->centre('51')),
            JournalLineDraft::credit($this->account('183'), '4000000'),
        ]);

        // Wages 6,000,000 — a component of value added, not an input to it.
        $this->postEntry([
            JournalLineDraft::debit($this->account('3111'), '6000000', $this->centre('51')),
            JournalLineDraft::credit($this->account('183'), '6000000'),
        ]);

        // Depreciation 2,000,000 — likewise a component.
        $this->postEntry([
            JournalLineDraft::debit($this->account('372'), '2000000', $this->centre('51')),
            JournalLineDraft::credit($this->account('183'), '2000000'),
        ]);

        // Indirect taxes 1,000,000, which bridge market prices to factor cost.
        $this->postEntry([
            JournalLineDraft::debit($this->account('3841'), '1000000', $this->centre('81')),
            JournalLineDraft::credit($this->account('183'), '1000000'),
        ]);
    }

    // ------------------------------------------------------------------ the set

    #[Test]
    public function every_prescribed_statement_is_defined(): void
    {
        $this->assertSame(9, StatementDefinition::where('statement_group', 'primary')->count());
        $this->assertSame(26, StatementDefinition::where('statement_group', 'analytical')->count());

        // The 26 analytical statements are numbered 1..26 with no gaps.
        $numbers = StatementDefinition::where('statement_group', 'analytical')
            ->orderBy('analytical_no')->pluck('analytical_no')->all();

        $this->assertSame(range(1, 26), $numbers);
    }

    #[Test]
    public function statements_awaiting_a_subledger_say_so_rather_than_showing_zeros(): void
    {
        $awaiting = StatementDefinition::whereNotNull('awaiting_module')->get();

        $this->assertGreaterThan(0, $awaiting->count());

        foreach ($awaiting as $definition) {
            $this->assertTrue($this->render($definition->code)->isAwaitingData());
        }
    }

    // ------------------------------------------------------------- value added

    /**
     * The highest-value test in the reporting work: it exercises the chart groupings end
     * to end. If a prefix set diverges between the two statements, this fails.
     */
    #[Test]
    public function the_value_added_statement_reconciles_to_its_distribution(): void
    {
        $this->seedTrading();

        $gva = $this->render('GVA');
        $distribution = $this->render('GVA_DISTRIBUTION');

        $this->assertNotNull($gva->total());
        $this->assertNotNull($distribution->total());

        $this->assertSame(
            0,
            bccomp($gva->total(), $distribution->total(), 4),
            sprintf(
                'GVA (%s) must equal its distribution (%s).',
                $gva->total(),
                $distribution->total(),
            ),
        );
    }

    #[Test]
    public function value_added_excludes_wages_and_depreciation_from_inputs(): void
    {
        $this->seedTrading();

        // Resources 20,000,000 − intermediate inputs 4,000,000 = 16,000,000 at market.
        // Then − indirect tax 1,000,000 = 15,000,000 at factor cost. Wages (6m) and
        // depreciation (2m) are components of value added, never deductions from it.
        $gva = $this->render('GVA');

        $this->assertSame(0, bccomp('15000000', $gva->total(), 4), 'GVA at factor cost should be 15,000,000, got '.$gva->total());
    }

    #[Test]
    public function the_distribution_accounts_for_every_factor_share(): void
    {
        $this->seedTrading();

        $distribution = $this->render('GVA_DISTRIBUTION');

        $labour = $distribution->figure(90);
        $depreciation = $distribution->figure(140);
        $surplus = $distribution->figure(150);

        $this->assertSame(0, bccomp('6000000', $labour, 4), 'Labour share should be the wages posted.');
        $this->assertSame(0, bccomp('2000000', $depreciation, 4));
        // 15,000,000 − 6,000,000 wages − 2,000,000 depreciation = 7,000,000 surplus.
        $this->assertSame(0, bccomp('7000000', $surplus, 4), 'Operating surplus is the balancing figure.');
    }

    // ------------------------------------------------------------ balance sheet

    #[Test]
    public function the_balance_sheet_balances(): void
    {
        // A capital injection touches both sides and nothing else, so the sheet must
        // balance exactly. Trading postings would leave the two sides apart by the
        // period result until the closing entry moves it into equity, which is a
        // different test.
        $this->postEntry([
            JournalLineDraft::debit($this->account('183'), '50000000'),
            JournalLineDraft::credit($this->account('211'), '50000000'),
        ]);

        $sheet = $this->render('BALANCE_SHEET');

        $assets = $sheet->figure(90);    // مجموع الموجودات
        $finance = $sheet->figure(190);  // مجموع مصادر التمويل

        $this->assertNotNull($assets);
        $this->assertNotNull($finance);
        $this->assertSame(0, bccomp('50000000', $assets, 4), 'Assets should be the cash injected.');
        $this->assertSame(
            0,
            bccomp($assets, $finance, 4),
            sprintf('The balance sheet must balance: assets %s against sources %s.', $assets, $finance),
        );
    }

    /** The contras are printed BELOW the totals: "لا يظهر لهما رصيد في الميزانية". */
    #[Test]
    public function contra_accounts_sit_outside_the_balance_sheet_totals(): void
    {
        $this->postEntry([
            JournalLineDraft::debit($this->account('1921'), '25000000'),
            JournalLineDraft::credit($this->account('2921'), '25000000'),
        ]);

        $sheet = $this->render('BALANCE_SHEET');

        $assetsTotal = $sheet->figure(90);
        $contraDebit = $sheet->figure(220);

        $this->assertSame(0, bccomp('0', $assetsTotal, 4), 'A memo entry must not move the asset total.');
        $this->assertSame(0, bccomp('25000000', $contraDebit, 4), 'The contra must still be reported, below the total.');
    }

    // ------------------------------------------------------------------ roll-up

    // ------------------------------------------------------------------ rendering

    #[Test]
    public function a_statement_renders_to_arabic_pdf(): void
    {
        $this->seedTrading();
        app()->setLocale('ar');

        $pdf = app(StatementPdf::class)
            ->render($this->render('GVA'), 'شركة العيدان', 'ar');

        $this->assertStringStartsWith('%PDF-', $pdf);
        file_put_contents(storage_path('app/gva-statement-ar.pdf'), $pdf);

        exec('command -v pdftotext', $probe, $code);

        if ($code !== 0) {
            $this->markTestSkipped('pdftotext unavailable.');
        }

        $out = storage_path('app/gva-statement-ar.txt');
        exec(sprintf('pdftotext -layout %s %s 2>/dev/null',
            escapeshellarg(storage_path('app/gva-statement-ar.pdf')), escapeshellarg($out)));

        $text = (string) file_get_contents($out);
        // mPDF writes shaped Presentation Forms-B; NFKC folds them back for assertion.
        $normalised = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;

        $this->assertStringContainsString('القيمة المضافة', $normalised);
        $this->assertStringContainsString('15,000,000', $normalised, 'The factor-cost total must print.');
    }

    /** The roll-up the standard's decimal numbering exists to enable. */
    #[Test]
    public function a_line_naming_a_parent_code_gathers_every_level_beneath_it(): void
    {
        $this->seedTrading();

        // 3352 sits under 335 under 33. A line naming 33 must pick it up.
        $statement = $this->render('REVENUE_EXPENSE');
        $serviceRequisites = $statement->figure(140);

        $this->assertSame(0, bccomp('4000000', $serviceRequisites, 4));
    }
}
