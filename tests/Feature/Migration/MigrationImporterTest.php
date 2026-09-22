<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvancePosition;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Migration\ControlRunner;
use App\Domain\Migration\File1Reader;
use App\Domain\Migration\MigrationImporter;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterService;
use App\Domain\Shared\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/**
 * M18 on a SYNTHETIC mini-ledger built here, in the layout of the authoritative
 * workbook. It proves the transformations and controls; it is not, and never
 * becomes, data of the company. UAT-051/052 on the real workbook run only when
 * SHH_FILE1_PATH points at it.
 */
class MigrationImporterTest extends TestCase
{
    use BuildsJournals;

    private string $workbook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workbook = $this->syntheticWorkbook([
            ['JV-0001', '2023-05-10', 'PRJ-01', 'CC-PM', 'Mr. Mohammed Abdul Redha Eidan Al-Shammari', 'تمويل من المساهم', '111002', '221001', 1_000_000, 1_000_000, 'OK', '1 – FUNDING / تمويل من المساهم', 'AMER-001', ''],
            ['JV-0002', '', 'PRJ-01', 'CC-PM', 'Mr. Yas Jassim Muslim (Abu Ahmed)', 'تحويل إلى سلفة', '112035', '111002', 400_000, 400_000, '⚠ NO DATE IN SOURCE — not fabricated', '2 – ADVANCE TRANSFER / تحويل إلى سلفة', 'AMER-002', ''],
            ['JV-0003', '2023-06-01', 'PRJ-01', '', 'Mr. Yas Jassim Muslim (Abu Ahmed)', 'تسوية', '610003', '112035', 500_000, 500_000, 'OK', '3 – SETTLEMENT / تسوية سلفة على حساب المصروف', 'AMER-003', 'POSSIBLE AMER DUPLICATE'],
            ['JV-0004', '2023-06-02', 'PRJ-01', 'CC-PM', 'Some Unlisted Vendor', 'دفع مباشر', '115050', '111002', 100_000, 100_000, 'OK', '2 – DIRECT PAYMENT / دفع مباشر من الخزنة', 'AMER-004', ''],
        ]);

        app(ParameterService::class)->change($this->manager(), ParameterCode::MigrationCutoffDate, '2023-06-30', CarbonImmutable::parse('2023-05-02'), 'MIG-CUTOFF', 'Cut-off agreed');
    }

    protected function tearDown(): void
    {
        @unlink($this->workbook);
        parent::tearDown();
    }

    /** @param  list<list<string|int>>  $rows */
    private function syntheticWorkbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'file1').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Journal Ledger');

        foreach (['SYNTHETIC TEST LEDGER', 'not company data', 'header'] as $title) {
            $writer->addRow(Row::fromValues([$title]));
        }

        $columns = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'I' => 7, 'K' => 8, 'L' => 9, 'N' => 10, 'Z' => 11, 'Q' => 12, 'U' => 13];

        foreach ($rows as $source) {
            $cells = array_fill(0, 29, '');
            foreach ($columns as $letter => $index) {
                $cells[File1Reader::columnIndex($letter)] = $source[$index];
            }
            $writer->addRow(Row::fromValues($cells));
        }

        $writer->close();

        return $path;
    }

    private function mapping(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'map').'.csv';
        file_put_contents($path, "source_name,counterparty\nSome Unlisted Vendor,NEW:CP-900\n");

        return $path;
    }

    #[Test]
    public function a_dry_run_reports_unmapped_names_and_writes_no_entry(): void
    {
        $result = app(MigrationImporter::class)->import($this->manager(), $this->workbook, commit: false);

        $this->assertSame(4, $result['rows']);
        $this->assertSame(['Some Unlisted Vendor'], $result['unresolved']);
        $this->assertSame(0, JournalHeader::query()->where('is_migration', true)->count());
    }

    #[Test]
    public function a_commit_needs_a_signoff_and_every_name_resolved(): void
    {
        $result = app(MigrationImporter::class)->import($this->manager(), $this->workbook, commit: true);

        $this->assertSame('failed', $result['run']->status);
        $this->assertSame(0, JournalHeader::query()->where('is_migration', true)->count());
    }

    #[Test]
    #[Group('UAT-042')]
    #[Group('UAT-052')]
    public function each_row_becomes_one_header_and_two_lines_with_nothing_invented(): void
    {
        $result = app(MigrationImporter::class)->import($this->manager(), $this->workbook, commit: true, signoff: 'AUDITOR-OK', mappingPath: $this->mapping());
        $this->assertSame('completed', $result['run']->status, implode("\n", $result['errors']));

        $headers = JournalHeader::query()->where('is_migration', true)->with('lines.responsibilityCenter')->orderBy('jv_no')->get();
        $this->assertCount(4, $headers);
        $this->assertSame(8, $headers->sum(fn ($h) => $h->lines->count()));
        $this->assertSame(['JV-0001', 'JV-0002', 'JV-0003', 'JV-0004'], $headers->pluck('jv_no')->all(), 'Source numbers are kept.');

        $undated = $headers[1];
        $this->assertNull($undated->txn_date, 'No date is invented.');
        $this->assertSame('no_date_in_source', $undated->date_status->value);
        $this->assertSame('2023-06-30', $undated->posting_date->toDateString(), 'Posted at the cut-off.');

        $this->assertSame('RC-02', $undated->lines[0]->responsibilityCenter?->code);
        $this->assertTrue($undated->lines[0]->rc_derived);
        $this->assertSame('RC-CORP', $headers[0]->lines[0]->responsibilityCenter?->code);

        $this->assertSame('EXC-SYS-03', $headers[2]->lines[0]->vr05_exemption_ref);
        $this->assertTrue(ControlException::query()->where('category', ExceptionCategory::MissingDimension)->exists());
        $this->assertSame(1, DuplicateFlag::query()->where('origin', 'migration')->count());

        $advance = Advance::query()->where('is_historic', true)->firstOrFail();
        $this->assertSame(-100_000, AdvancePosition::of($advance)->outstanding, 'The net credit on the advance survives: never forced to zero.');

        $this->assertTrue(ControlException::query()->where('source_code', 'EXC-OPEN-01')->where('status', 'open')->exists());
    }

    #[Test]
    public function the_controls_compare_the_system_with_the_spec_figures(): void
    {
        $result = app(MigrationImporter::class)->import($this->manager(), $this->workbook, commit: true, signoff: 'OK', mappingPath: $this->mapping());
        $controls = collect(app(ControlRunner::class)->run($result['run']))->keyBy('control_code');

        $this->assertCount(30, $controls);
        $this->assertSame('pass', $controls['MC-05']->status, 'Debit − credit is nil.');
        $this->assertSame('pass', $controls['MC-28']->status, 'No example rows.');
        $this->assertSame('fail', $controls['MC-01']->status, 'Four synthetic rows are not the 1,195 of the real ledger.');
        $this->assertSame([3979086000, 4078882000, 99796000], ControlRunner::numbers('Dr 3,979,086,000 / Cr 4,078,882,000 / net credit 99,796,000'));
        $this->assertSame([1159, 20, 16], ControlRunner::numbers('PRJ-01 1,159 entries · PRJ-02 20 · PRJ-03 16'));
        $this->assertSame([-99796000], ControlRunner::numbers('−99,796,000'));
        $this->assertSame([62528000, 803206750], ControlRunner::numbers('112101 = 62,528,000 · 112102 = 803,206,750'));
    }

    #[Test]
    #[Group('VR-54')]
    public function no_migration_entry_after_go_live(): void
    {
        app(ParameterService::class)->change($this->manager(), ParameterCode::GoLiveDate, now()->subDay()->toDateString(), CarbonImmutable::parse('2023-05-02'), 'GO-LIVE', 'Live');

        $this->expectException(RuleViolation::class);
        app(MigrationImporter::class)->import($this->manager(), $this->workbook, commit: true, signoff: 'OK', mappingPath: $this->mapping());
    }

    #[Test]
    #[Group('UAT-051')]
    public function the_authoritative_workbook_reconciles_when_supplied(): void
    {
        $path = (string) getenv('SHH_FILE1_PATH');

        if ($path === '' || ! is_file($path)) {
            $this->markTestSkipped('SHH_FILE1_PATH is not set: the authoritative workbook has not been supplied.');
        }

        $result = app(MigrationImporter::class)->import($this->manager(), $path, commit: true, signoff: 'UAT-051', mappingPath: getenv('SHH_FILE1_MAPPING') ?: null);
        $failing = collect(app(ControlRunner::class)->run($result['run']))->where('status', 'fail')->pluck('control_code')->all();

        $this->assertSame([], $failing, 'All thirty controls read Pass.');
    }
}
