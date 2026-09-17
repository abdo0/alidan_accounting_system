<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converts the chart of accounts to the Iraqi Unified Accounting System
     * (النظام المحاسبي الموحد, Board of Supreme Audit, 2nd ed. 2011).
     *
     * The UAS differs from the IFRS-style model this schema was first built around:
     *   - Nine top-level classes, not five. Classes 5-9 are cost-centre controls.
     *   - No equity class at all: رأس المال and الاحتياطيات are liabilities.
     *   - Codes are hierarchical decimal, 1-6 digits, where the PARENT IS THE PREFIX,
     *     and the digit 0 is never used within a level.
     *   - Posting happens at level 3 or deeper, at the leaf of a branch.
     *
     * Safe to run destructively because nothing has been posted yet.
     */
    public function up(): void
    {
        // --- account_class: the UAS nine ------------------------------------------
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_class_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE accounts ADD CONSTRAINT accounts_class_valid CHECK (account_class IN (
                'asset',            -- 1 الموجودات
                'liability',        -- 2 المطلوبات (capital and reserves live here)
                'use',              -- 3 الاستخدامات
                'resource',         -- 4 الموارد
                'cc_production',    -- 5 مراقبة مراكز الإنتاج
                'cc_prod_services', -- 6 مراقبة مراكز الخدمات الإنتاجية
                'cc_marketing',     -- 7 مراقبة مراكز الخدمات التسويقية
                'cc_admin',         -- 8 مراقبة مراكز الخدمات الإدارية
                'cc_capital'        -- 9 مراقبة مراكز العمليات الرأسمالية
            ))
        SQL);

        // --- statement families ----------------------------------------------------
        // BS = الميزانية العامة, RESULT = حسابات النتيجة, MEMO = the 19/29 contras which
        // are presented BELOW the balance sheet totals, CC = cost-centre controls which
        // appear in neither.
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_statement_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE accounts ADD CONSTRAINT accounts_statement_valid
            CHECK (statement IN ('BS','RESULT','MEMO','CC','NONE'))
        SQL);

        Schema::table('accounts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('account_level')->nullable()->after('account_class');
            // The paired contra account: 19X <-> 29X. Pairing is by TRAILING digits, not
            // by the word مقابل, which sits on different sides in different pairs.
            $table->string('contra_pair_code', 30)->nullable()->after('contra_of_account_id');
        });

        // The level IS the code length; enforce rather than merely document it.
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_level_valid CHECK (account_level BETWEEN 1 AND 6)');
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_level_matches_code CHECK (account_level = length(code))');
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_code_digits CHECK (code ~ '^[1-9]{1,6}$')");
        // Posting runs from the third level to the leaf.
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_postable_depth CHECK (NOT is_postable OR account_level >= 3)');

        // In the UAS the subledger detail IS the deeper levels of a controlled branch,
        // so a control account is frequently a heading with children and therefore not
        // itself postable. The original constraint assumed otherwise.
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_control_is_postable');

        // --- activity classification on the posting line ----------------------------
        // Required by المبادئ والأسس: distinguish current from investment activity, and
        // ordinary from exceptional. كشف العمليات الجارية and the cash flow statement
        // both depend on it.
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->string('activity_type', 12)->nullable()->after('project_id');
            $table->string('activity_nature', 12)->nullable()->after('activity_type');
        });

        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_activity_type_valid CHECK (activity_type IS NULL OR activity_type IN ('current','investment'))");
        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_activity_nature_valid CHECK (activity_nature IS NULL OR activity_nature IN ('ordinary','exceptional'))");

        // --- cost centres: the UAS taxonomy ----------------------------------------
        DB::statement('ALTER TABLE cost_centres DROP CONSTRAINT IF EXISTS cost_centre_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE cost_centres ADD CONSTRAINT cost_centre_type_valid
            CHECK (cost_centre_type IN ('production','prod_service','marketing','admin','capital'))
        SQL);

        Schema::table('cost_centres', function (Blueprint $table): void {
            // Ties the centre to its control class 5-9, which is what makes the
            // composite 531-style statutory code derivable.
            $table->unsignedSmallInteger('control_class')->nullable()->after('cost_centre_type');
            // The standard requires a centre to BE a responsibility unit.
            $table->string('responsibility_unit', 150)->nullable()->after('branch');
        });

        DB::statement('ALTER TABLE cost_centres ADD CONSTRAINT cc_control_class_valid CHECK (control_class IS NULL OR control_class BETWEEN 5 AND 9)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cost_centres DROP CONSTRAINT IF EXISTS cc_control_class_valid');
        Schema::table('cost_centres', function (Blueprint $table): void {
            $table->dropColumn(['control_class', 'responsibility_unit']);
        });

        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS jl_activity_nature_valid');
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS jl_activity_type_valid');
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropColumn(['activity_type', 'activity_nature']);
        });

        foreach ([
            'accounts_postable_depth', 'accounts_code_digits',
            'accounts_level_matches_code', 'accounts_level_valid',
        ] as $constraint) {
            DB::statement("ALTER TABLE accounts DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['account_level', 'contra_pair_code']);
        });
    }
};
