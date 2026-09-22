<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The approved Chart of Accounts (M01, Document C tab 04).
     *
     * 145 accounts transcribed from the authoritative workbook, plus the 15 group
     * codes they name as parents (Document A Appendix A), stored as level-1,
     * non-posting group rows so the tree has a single self-referencing parent.
     *
     * The derived attributes of Document B §4.1 are columns, not code: the
     * requires_* flags drive VR-07, VR-08 and VR-29 declaratively, so no validation
     * rule carries a list of account codes.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 10);
            $table->foreignId('parent_id')->nullable()->constrained('accounts');
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('account_type', 12);
            $table->unsignedSmallInteger('account_level');
            $table->boolean('is_group')->default(false);
            $table->boolean('is_posting')->default(true);
            $table->boolean('is_active')->default(true);
            $table->char('normal_balance', 2);
            $table->string('fs_line_code', 20)->nullable();
            $table->string('reporting_group', 120)->nullable();
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_advance_account')->default(false);
            $table->boolean('is_control_account')->default(false);
            $table->string('control_subledger', 120)->nullable();
            // Which subledger reads this control account: derived from the chart's
            // "Control Account (subledger)" column, so reports select accounts by
            // attribute, never by code.
            $table->string('subledger', 20)->nullable();
            $table->boolean('requires_counterparty')->default(false);
            $table->boolean('requires_advance_holder')->default(false);
            $table->boolean('requires_contract')->default(false);
            // CONF-07: the Execution / Operating / Administrative purpose of a
            // custodian's advance triplet, kept as an attribute of the account.
            $table->string('purpose', 40)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index('parent_id');
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_valid CHECK (account_type IN ('asset','liability','equity','revenue','expense'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_normal_balance_valid CHECK (normal_balance IN ('dr','cr'))");
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_level_valid CHECK (account_level BETWEEN 1 AND 3)');
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_code_numeric CHECK (code ~ '^[0-9]{6}$')");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_subledger_valid CHECK (subledger IS NULL OR subledger IN ('contractor','custodian','personal_receivable','government','shareholder'))");
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_group_not_posting CHECK (NOT (is_group AND is_posting))');
        DB::statement('CREATE INDEX accounts_name_trgm ON accounts USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX accounts_name_ar_trgm ON accounts USING gin (name_ar gin_trgm_ops)');

        // An account is deactivated, never deleted: a deletion would orphan every
        // mapping and every historical line that names it.
        DB::statement(<<<'SQL'
            CREATE TRIGGER accounts_no_delete BEFORE DELETE ON accounts
                FOR EACH ROW EXECUTE FUNCTION refuse_change('deactivate an account instead of deleting it')
        SQL);

        DB::statement('SELECT attach_audit(?)', ['accounts']);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
