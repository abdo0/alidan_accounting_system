<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Account;
use App\Domain\Reporting\Export\ExcelExporter;
use App\Domain\Reporting\Export\PdfExporter;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportMapping;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Filament\Pages\Reporting\ReportsIndex;
use App\Filament\Pages\Reporting\ReportViewer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M07: the mapping-driven reporting engine (Document B §4.6, §6). */
class ReportingEngineTest extends TestCase
{
    use BuildsJournals;

    /** @param  array<string, mixed>  $filters */
    private function report(string $code, array $filters = []): ReportResult
    {
        return app(ReportRegistry::class)->get($code)->run(FilterSet::fromArray($filters), $this->manager());
    }

    /** A small ledger touching assets, liabilities, a cost and revenue. */
    private function ledger(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(500_000_000));
        $this->postEntry('TT-05', [
            $this->line('115050', debit: 120_000_000, extra: ['counterparty_id' => $this->counterpartyId('CN-03'), 'capex_opex' => 'capex']),
            $this->line('111002', credit: 120_000_000, extra: ['cash_account_id' => $this->cashId()]),
        ]);
        $this->postEntry('TT-05', [
            $this->line('610006', debit: 5_000_000, extra: ['counterparty_id' => $this->counterpartyId('CN-05'), 'capex_opex' => 'opex']),
            $this->line('111002', credit: 5_000_000, extra: ['cash_account_id' => $this->cashId()]),
        ]);
    }

    #[Test]
    #[Group('UAT-044')]
    #[Group('VR-56')]
    public function the_trial_balance_proves_in_every_direction(): void
    {
        $this->ledger();

        $tb = $this->report('RPT-04', ['from' => '2026-01-01', 'to' => '2026-12-31']);

        $this->assertCount(3, $tb->controls);
        $this->assertTrue($tb->controlsPass(), 'All three balance proofs agree.');
        $this->assertSame(625_000_000, $tb->rowsOfStyle(Row::TOTAL)[0]->cells['period_dr']);

        $safe = $tb->findRow('code', '111002');
        $this->assertSame(375_000_000, $safe?->cells['close_dr']);
    }

    #[Test]
    public function opening_columns_hold_what_was_posted_before_the_range(): void
    {
        $this->ledger();

        $tb = $this->report('RPT-04', ['from' => '2026-04-01', 'to' => '2026-12-31']);
        $loan = $tb->findRow('code', '221001');

        $this->assertNotNull($loan);
        $this->assertSame(500_000_000, $loan->cells['open_cr']);
        $this->assertSame(0, $loan->cells['period_cr']);
        $this->assertSame(500_000_000, $loan->cells['close_cr']);
    }

    #[Test]
    #[Group('UAT-045')]
    public function the_statement_of_financial_position_balances_from_the_mapping(): void
    {
        $this->ledger();

        $sfp = $this->report('RPT-06', ['as_at' => '2026-12-31']);

        $this->assertTrue($sfp->controlsPass(), 'Assets − (Liabilities + Equity) = 0.');
        $this->assertSame(495_000_000, $sfp->findRow('code', 'SFP-A-900')?->cells['value']);
        $this->assertSame(120_000_000, $sfp->findRow('code', 'SFP-A-040')?->cells['value']);
        $this->assertSame(500_000_000, $sfp->findRow('code', 'SFP-L-140')?->cells['value']);
        $this->assertSame(-5_000_000, $sfp->findRow('code', 'SFP-E-250')?->cells['value'], 'The current result is the P&L to date.');
    }

    #[Test]
    public function net_retained_revenue_is_revenue_less_the_government_share(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(1));
        $journal = $this->draft('TT-27', [
            $this->line('111002', debit: 100_000_000, extra: ['cash_account_id' => $this->cashId()]),
            $this->line('410001', credit: 100_000_000, extra: ['revenue_eligible' => true, 'counterparty_id' => $this->counterpartyId('CN-05')]),
        ]);
        $this->postEntryFromDraft($journal);

        $share = $this->draft('TT-28', [
            $this->line('510001', debit: 20_000_000, extra: ['capex_opex' => 'opex']),
            $this->line('213001', credit: 20_000_000, extra: ['counterparty_id' => $this->counterpartyId('GOV-01')]),
        ]);
        $this->postEntryFromDraft($share);

        $pl = $this->report('RPT-07', ['from' => '2026-01-01', 'to' => '2026-12-31']);

        $this->assertSame(100_000_000, $pl->findRow('code', 'PL-010')?->cells['value']);
        $this->assertSame(20_000_000, $pl->findRow('code', 'PL-020')?->cells['value']);
        $this->assertSame(80_000_000, $pl->findRow('code', 'PL-030')?->cells['value']);
        $this->assertSame(80_000_000, $pl->findRow('code', 'PL-900')?->cells['value']);
    }

    private function postEntryFromDraft(JournalHeader $draft): void
    {
        app(JournalWorkflow::class)->post($this->manager(), $this->submitReviewApprove($draft));
    }

    #[Test]
    #[Group('UAT-046')]
    #[Group('UAT-047')]
    public function every_figure_drills_down_and_the_filters_carry_into_the_child(): void
    {
        $this->ledger();
        $filters = ['as_at' => '2026-12-31', 'projects' => [(string) $this->projectId()]];

        $sfp = $this->report('RPT-06', $filters);
        $cip = $sfp->findRow('code', 'SFP-A-040');
        $target = $cip?->drillFor('value');

        $this->assertSame('RPT-04', $target['report'] ?? null);
        $this->assertSame([(string) $this->projectId()], $target['filters']['projects'], 'The project filter carries into the trial balance.');

        $tb = $this->report('RPT-04', $target['filters']);
        $account = $tb->findRow('code', '115050');
        $ledgerTarget = $account?->drillFor('close_dr');

        $this->assertSame('RPT-05', $ledgerTarget['report'] ?? null);

        $ledger = $this->report('RPT-05', $ledgerTarget['filters']);
        $movement = $ledger->rowsOfStyle(Row::DETAIL)[0];

        $this->assertArrayHasKey('journal', $movement->drillFor('debit') ?? []);
    }

    #[Test]
    public function exports_render_the_same_result_with_the_filters_printed(): void
    {
        $this->ledger();
        $result = $this->report('RPT-04', ['from' => '2026-01-01', 'to' => '2026-12-31', 'projects' => [(string) $this->projectId()]]);

        $path = tempnam(sys_get_temp_dir(), 'tb').'.xlsx';
        app(ExcelExporter::class)->export($result, $path, 'Test');
        $this->assertGreaterThan(3000, filesize($path));

        $zip = new \ZipArchive;
        $zip->open($path);
        $sheet = (string) $zip->getFromName('xl/sharedStrings.xml').(string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString(__('reports.filters.projects'), $sheet);
        $this->assertStringContainsString('111002', $sheet);

        $pdf = app(PdfExporter::class)->export($result, 'Test');
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    #[Test]
    public function every_active_posting_account_maps_to_exactly_one_statement_line(): void
    {
        $posting = Account::query()->postable()->pluck('id');

        foreach ($posting as $id) {
            $this->assertSame(1, ReportMapping::query()->where('account_id', $id)->whereIn('statement', ['SFP', 'PL'])->whereNull('effective_to')->count());
        }

        $this->assertCount(141, $posting);
    }

    #[Test]
    #[Group('VR-55')]
    public function no_report_class_carries_an_account_code(): void
    {
        foreach (glob(app_path('Domain/Reporting/{,*/}*.php'), GLOB_BRACE) ?: [] as $file) {
            $this->assertDoesNotMatchRegularExpression(
                "/'[1-6]\\d{5}'/",
                (string) file_get_contents($file),
                basename($file).' hardcodes an account code; statement accounts come from report_mappings.',
            );
        }
    }

    #[Test]
    public function the_report_pages_open(): void
    {
        $this->ledger();
        $this->actingAs($this->manager());

        $this->get(ReportsIndex::getUrl())->assertSuccessful()->assertSee('RPT-04');

        foreach (array_keys(app(ReportRegistry::class)->all()) as $code) {
            if ($code === 'RPT-32') {
                continue;
            }

            $this->get(ReportViewer::getUrl(['report' => $code]))->assertSuccessful();
        }
    }

    #[Test]
    public function the_audit_trail_needs_the_audit_permission(): void
    {
        $this->actingAs($this->userWithRole('accountant'));
        $this->get(ReportViewer::getUrl(['report' => 'RPT-32']))->assertForbidden();

        $this->actingAs($this->userWithRole('internal_auditor'));
        $this->get(ReportViewer::getUrl(['report' => 'RPT-32']))->assertSuccessful();
    }
}
