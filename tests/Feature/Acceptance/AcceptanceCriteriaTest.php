<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\Posting\PostingService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** Document B §11: the technical acceptance criteria that code can prove. */
class AcceptanceCriteriaTest extends TestCase
{
    use BuildsJournals;

    #[Test]
    public function criterion_1_the_ledger_balances_for_every_period_and_in_total(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(1_000_000));
        $this->postEntry('TT-01', $this->fundingLines(2_000), ['posting_date' => '2026-05-10', 'txn_date' => '2026-05-10']);

        $this->assertSame(0, PostingService::ledgerDifference());

        $perPeriod = DB::table('journal_lines as jl')->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->groupBy('jh.period_id')->selectRaw('sum(jl.debit) - sum(jl.credit) AS d')->pluck('d');

        $this->assertTrue($perPeriod->every(fn ($d): bool => (int) $d === 0));
    }

    #[Test]
    #[Group('VR-43')]
    public function criterion_12_the_government_share_rate_lives_only_in_the_parameters(): void
    {
        $finder = (new Finder)->files()->in([app_path(), config_path(), database_path('migrations'), database_path('seeders'), resource_path(), base_path('routes')])->name('*.php');
        $offending = [];

        foreach ($finder as $file) {
            foreach (preg_split('/\R/', $file->getContents()) ?: [] as $number => $line) {
                if (preg_match('/share|gov/i', $line) === 1 && preg_match('/(?<![\\d.])0?\\.20?(?![\\d])|\\b20\\s?%/', $line) === 1) {
                    $offending[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offending, 'A share rate appears outside the parameters table.');
    }

    #[Test]
    public function criterion_13_no_user_facing_label_is_hardcoded(): void
    {
        $finder = (new Finder)->files()->in(app_path('Filament'))->name('*.php');
        $offending = [];

        foreach ($finder as $file) {
            if (preg_match_all("/->(label|title|placeholder|helperText|description|heading)\\('([^'_][^']*)'\\)/", $file->getContents(), $m) > 0) {
                foreach ($m[2] as $literal) {
                    if (preg_match('/[A-Za-z\\x{0600}-\\x{06FF}]{3,}/u', $literal) === 1) {
                        $offending[] = $file->getRelativePathname().': '.$literal;
                    }
                }
            }
        }

        $this->assertSame([], $offending, 'Labels must come from the language files.');
    }

    #[Test]
    #[Group('VR-55')]
    #[Group('VR-57')]
    public function no_figure_is_stored_outside_journal_lines(): void
    {
        $money = DB::select(<<<'SQL'
            SELECT c.table_name, c.column_name FROM information_schema.columns c
             WHERE c.table_schema = 'public' AND c.domain_name IN ('iqd_amount', 'iqd_signed')
        SQL);

        $allowed = [
            'journal_lines' => ['debit', 'credit', 'source_amount', 'amount_difference'],
            'contracts' => ['contract_value'],
            'contract_amendments' => ['value_change'],
            'work_packages' => ['budget_value'],
            'funding_batches' => ['source_amount'],
            'duplicate_flags' => ['value_at_risk'],
            'exceptions' => ['amount'],
            'advance_settlements' => ['claimed_amount'],
            'reconciliations' => ['actual_balance'],
        ];

        foreach ($money as $column) {
            $this->assertContains($column->column_name, $allowed[$column->table_name] ?? [], "{$column->table_name}.{$column->column_name} would store a figure the ledger should produce.");
        }
    }

    #[Test]
    #[Group('VR-56')]
    public function criterion_8_the_application_role_cannot_rewrite_the_audit_log(): void
    {
        $triggers = DB::table('pg_trigger')->where('tgrelid', DB::raw("'audit_log'::regclass"))->pluck('tgname')->all();

        $this->assertContains('audit_log_no_update_delete', $triggers);
        $this->assertContains('audit_log_no_truncate', $triggers);
    }

    #[Test]
    #[Group('VR-58')]
    #[Group('VR-59')]
    #[Group('VR-60')]
    public function the_control_records_themselves_cannot_be_deleted(): void
    {
        foreach (['exceptions' => 'exceptions_no_delete', 'duplicate_flags' => 'duplicate_flags_guard', 'accounts' => 'accounts_no_delete', 'documents' => 'documents_immutable'] as $table => $trigger) {
            $this->assertTrue(DB::table('pg_trigger')->where('tgrelid', DB::raw("'{$table}'::regclass"))->where('tgname', $trigger)->exists(), "{$table} lacks {$trigger}.");
        }
    }
}
