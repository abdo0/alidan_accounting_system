<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carries the account code on the posting line itself.
     *
     * Accountants working under the Unified Accounting System quote codes, not names --
     * every register in الفصل الخامس has a رقم الدليل column, and the prescribed vouchers
     * lay the code out digit by digit. Denormalising it is safe here in a way it usually
     * is not: the standard forbids reusing a code, so a posted line keeps the code it
     * was posted under permanently, and the ledger is append-only besides.
     */
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->string('account_code', 6)->nullable()->after('account_id');
            // The composite statutory code the cost distribution grid is keyed on:
            // <control class 5-9><use element, 2 digits>. ٥٣١ = salaries, production.
            $table->string('cost_account_code', 3)->nullable()->after('cost_centre_id');
        });

        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_account_code_format CHECK (account_code IS NULL OR account_code ~ '^[1-9]{1,6}$')");
        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_cost_account_code_format CHECK (cost_account_code IS NULL OR cost_account_code ~ '^[5-9][1-9]{2}$')");

        // Reporting reads the code directly; no join to accounts.
        DB::statement('CREATE INDEX jl_account_code_idx ON journal_lines (entity_id, account_code, entry_date) WHERE posted_at IS NOT NULL');
        DB::statement('CREATE INDEX jl_cost_account_code_idx ON journal_lines (entity_id, cost_account_code, entry_date) WHERE cost_account_code IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS jl_cost_account_code_idx');
        DB::statement('DROP INDEX IF EXISTS jl_account_code_idx');
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS jl_cost_account_code_format');
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS jl_account_code_format');

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropColumn(['account_code', 'cost_account_code']);
        });
    }
};
