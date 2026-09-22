<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materialised balances. Reports read this, never a sum over journal_lines: a
     * trial balance then costs an indexed scan of a table that grows with
     * accounts x periods x cost centres rather than with transactions.
     *
     * project_id is deliberately NOT part of the key. Projects are high-cardinality
     * by design (docs/05 §5.7); keying them here would multiply the row count and
     * invert the benefit the table exists for. Project reporting reads journal_lines.
     */
    public function up(): void
    {
        Schema::create('gl_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('fiscal_period_id')->constrained();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('cost_centre_id')->nullable()->constrained();
            $table->char('currency_code', 3)->default('IQD');

            // Written only by the posting UPSERT.
            $table->decimal('period_debit', 20, 4)->default(0);
            $table->decimal('period_credit', 20, 4)->default(0);
            $table->decimal('functional_period_debit', 20, 4)->default(0);
            $table->decimal('functional_period_credit', 20, 4)->default(0);

            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['entity_id', 'fiscal_period_id', 'account_id']);
            $table->index(['entity_id', 'cost_centre_id', 'fiscal_period_id']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        // NULLS NOT DISTINCT is essential, not cosmetic. Under the default
        // NULLS DISTINCT every posting with no cost centre would insert a NEW row
        // instead of accumulating into the existing one, because NULL <> NULL: the
        // table would grow one row per posting and the UPSERT would never match.
        DB::statement(<<<'SQL'
            ALTER TABLE gl_balances
            ADD CONSTRAINT gl_balances_key
            UNIQUE NULLS NOT DISTINCT
            (entity_id, fiscal_period_id, account_id, cost_centre_id, currency_code)
        SQL);

        DB::statement('SELECT attach_audit(?)', ['gl_balances']);
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_balances');
    }
};
