<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reporting entity and its calendar (M00, M15).
     *
     * SHH-01 is the only company at go-live. The table exists so that consolidation
     * can be added without restructuring (Document C tab 20).
     *
     * Period status follows Document B §4.11: Open -> Soft Close -> Final Close ->
     * Locked. Leaving Locked is refused for every principal except the table owner,
     * which the runtime role is not -- the only way back is the audited
     * admin_unlock_period() function, run by an administrator at the database.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->text('operating_model')->nullable();
            $table->char('currency', 3)->default('IQD');
            $table->boolean('decimals_allowed')->default(false);
            $table->date('accounting_start');
            $table->foreignId('parent_id')->nullable()->constrained('companies');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // IQD only: the FX rate parameter exists but no transaction carries a
        // foreign currency.
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_currency_iqd CHECK (currency = 'IQD')");

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('year_code', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'year_code']);
        });

        DB::statement("ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_status_valid CHECK (status IN ('open','closed'))");
        DB::statement('ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_dates CHECK (starts_on <= ends_on)');

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('fiscal_year_id')->constrained();
            $table->string('period_code', 7);            // 2023-05
            $table->unsignedSmallInteger('period_no');   // calendar month, 1..12
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('open');
            $table->foreignId('soft_closed_by')->nullable()->constrained('users');
            $table->timestampTz('soft_closed_at')->nullable();
            $table->foreignId('final_closed_by')->nullable()->constrained('users');
            $table->timestampTz('final_closed_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestampTz('locked_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users');
            $table->timestampTz('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'period_code']);
            $table->index(['company_id', 'starts_on', 'ends_on']);
        });

        DB::statement("ALTER TABLE accounting_periods ADD CONSTRAINT accounting_periods_status_valid CHECK (status IN ('open','soft_close','final_close','locked'))");
        DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT accounting_periods_dates CHECK (starts_on <= ends_on)');
        DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT accounting_periods_month CHECK (period_no BETWEEN 1 AND 12)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_period_lock_guard()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'locked' AND NEW.status IS DISTINCT FROM 'locked'
                   AND current_user <> (SELECT tableowner FROM pg_tables WHERE tablename = 'accounting_periods' LIMIT 1)
                THEN
                    RAISE EXCEPTION 'Period % is locked; only admin_unlock_period() may reopen it', OLD.period_code
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER accounting_period_lock_guard BEFORE UPDATE ON accounting_periods
                FOR EACH ROW EXECUTE FUNCTION accounting_period_lock_guard()
        SQL);

        // The documented administrative action for a locked period. SECURITY
        // DEFINER so it runs as the owner; EXECUTE is revoked from PUBLIC.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION admin_unlock_period(p_period_id bigint, p_reason text)
            RETURNS void AS $$
            BEGIN
                IF coalesce(trim(p_reason), '') = '' THEN
                    RAISE EXCEPTION 'A reason is required to unlock a period';
                END IF;
                PERFORM set_config('app.audit_action', 'period_admin_unlock', true);
                PERFORM set_config('app.audit_reason', p_reason, true);
                UPDATE accounting_periods
                   SET status = 'final_close', reopen_reason = p_reason, reopened_at = now()
                 WHERE id = p_period_id AND status = 'locked';
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER
        SQL);

        DB::statement('REVOKE EXECUTE ON FUNCTION admin_unlock_period(bigint, text) FROM PUBLIC');

        foreach (['companies', 'fiscal_years', 'accounting_periods'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS admin_unlock_period(bigint, text)');
        Schema::dropIfExists('accounting_periods');
        DB::statement('DROP FUNCTION IF EXISTS accounting_period_lock_guard()');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('companies');
    }
};
