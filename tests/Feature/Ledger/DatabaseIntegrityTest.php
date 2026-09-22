<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/**
 * Document B §11, acceptance criteria 6 and 7: what the storage layer enforces on
 * its own, for every path that bypasses the application -- including raw SQL.
 */
class DatabaseIntegrityTest extends TestCase
{
    use BuildsJournals;

    /** @param  \Closure(): mixed  $statement */
    private function refusedBySql(\Closure $statement, string $expected): void
    {
        try {
            DB::transaction($statement);
            $this->fail("The database accepted what {$expected} should refuse.");
        } catch (QueryException $e) {
            $this->assertStringContainsString($expected, $e->getMessage());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function constraints(): array
    {
        return [
            'one side' => ['journal_lines', 'ck_one_side'],
            'unique line' => ['journal_lines', 'uq_line'],
            'undated' => ['journal_headers', 'ck_undated'],
            'dates' => ['journal_headers', 'ck_dates'],
            'posted has number' => ['journal_headers', 'ck_posted_has_jv'],
            'reviewer is not creator' => ['journal_headers', 'ck_reviewer_not_creator'],
            'approver is not creator' => ['journal_headers', 'ck_approver_not_creator'],
        ];
    }

    #[Test]
    #[DataProvider('constraints')]
    public function the_documented_constraints_exist(string $table, string $name): void
    {
        $this->assertTrue(
            DB::table('pg_constraint')->where('conrelid', DB::raw("'{$table}'::regclass"))->where('conname', $name)->exists(),
            "{$table}.{$name} is missing.",
        );
    }

    #[Test]
    #[Group('VR-18')]
    public function a_posted_entry_cannot_be_edited_or_deleted_by_raw_sql(): void
    {
        $posted = $this->postEntry('TT-01', $this->fundingLines(1_000));

        $this->refusedBySql(fn () => DB::update('UPDATE journal_headers SET description_ar = ? WHERE id = ?', ['tampered', $posted->id]), 'VR-18');
        $this->refusedBySql(fn () => DB::update('UPDATE journal_lines SET debit = debit + 1 WHERE journal_header_id = ?', [$posted->id]), 'VR-18');
        $this->refusedBySql(fn () => DB::delete('DELETE FROM journal_headers WHERE id = ?', [$posted->id]), 'VR-18');
        $this->refusedBySql(fn () => DB::delete('DELETE FROM journal_lines WHERE journal_header_id = ?', [$posted->id]), 'VR-18');

        $this->assertSame('قيد اختبار', DB::table('journal_headers')->where('id', $posted->id)->value('description_ar'));
    }

    #[Test]
    #[Group('VR-19')]
    public function a_fractional_dinar_is_rejected_by_the_column_type(): void
    {
        $draft = $this->draft('TT-01', $this->fundingLines(1_000));

        $this->refusedBySql(fn () => DB::update('UPDATE journal_lines SET debit = 1000.5 WHERE journal_header_id = ? AND line_no = 1', [$draft->id]), 'iqd_amount');
    }

    #[Test]
    #[Group('VR-03')]
    public function a_line_with_both_sides_is_rejected_by_the_database(): void
    {
        $draft = $this->draft('TT-01', $this->fundingLines(1_000));

        $this->refusedBySql(fn () => DB::update('UPDATE journal_lines SET credit = 5 WHERE journal_header_id = ? AND line_no = 1', [$draft->id]), 'ck_one_side');
    }

    #[Test]
    #[Group('VR-01')]
    public function an_unbalanced_entry_cannot_leave_draft_even_by_raw_sql(): void
    {
        $draft = $this->draft('TT-01', $this->fundingLines(1_000));
        DB::update('UPDATE journal_lines SET credit = 999 WHERE journal_header_id = ? AND line_no = 2', [$draft->id]);

        $this->refusedBySql(function () use ($draft): void {
            DB::statement('SET CONSTRAINTS jh_balanced, jl_balanced IMMEDIATE');
            DB::update("UPDATE journal_headers SET status = 'submitted' WHERE id = ?", [$draft->id]);
        }, 'VR-01');
    }

    #[Test]
    #[Group('UAT-042')]
    public function an_undated_entry_must_say_why(): void
    {
        $draft = $this->draft('TT-01', $this->fundingLines(1_000));

        $this->refusedBySql(fn () => DB::update("UPDATE journal_headers SET txn_date = NULL, date_status = 'ok' WHERE id = ?", [$draft->id]), 'ck_undated');
    }

    #[Test]
    public function the_ledger_holds_no_stored_balance_table(): void
    {
        foreach (['gl_balances', 'account_balances', 'balances', 'trial_balance'] as $table) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($table), "{$table} would be a stored balance (Document B §1.1).");
        }

        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('journal_headers', 'total_debit'));
    }
}
