<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The prescribed statements of الفصل الرابع, held as data rather than code.
     *
     * Nine primary statements -- three of them mutually exclusive by entity type -- plus
     * 26 analytical ones, each with a fixed line-to-account mapping laid down by the
     * standard. Hard-coding 35 statements would be unreadable and unmaintainable; and
     * because the mapping is prescribed rather than invented, it is data by nature.
     */
    public function up(): void
    {
        Schema::create('statement_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar', 200);
            $table->string('name_en', 200)->nullable();
            // إنموذج رقم (١) / (٢) as printed on the form itself.
            $table->string('form_no', 10)->nullable();
            $table->string('statement_group', 20);          // primary | analytical
            $table->unsignedSmallInteger('analytical_no')->nullable();  // كشف رقم (n)
            // Which entity types use this statement. Industrial, commercial and service
            // companies with a cost system use إنموذج ١; service companies without one
            // use إنموذج ٢; contractors use the undertakings account.
            $table->jsonb('entity_types')->nullable();
            $table->boolean('requires_cost_centres')->default(false);
            // Set where the statement's data source is a subledger not yet built. The
            // statement still renders, with a visible note rather than silent zeros.
            $table->string('awaiting_module', 40)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE statement_definitions ADD CONSTRAINT stmt_group_valid CHECK (statement_group IN ('primary','analytical'))");
        DB::statement('ALTER TABLE statement_definitions ADD CONSTRAINT stmt_analytical_no_valid CHECK (analytical_no IS NULL OR analytical_no BETWEEN 1 AND 26)');

        Schema::create('statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label_ar', 300);
            $table->string('label_en', 300)->nullable();
            // header  - a caption with no figure
            // accounts- sums the account_codes prefixes
            // formula - computed from other lines by their sequence, e.g. 'L30 - L40'
            // subtotal/total - a rule and a bold figure
            // note    - free text printed beneath
            $table->string('line_type', 20);
            /*
             * PREFIXES, not exact codes. A line naming 41 picks up 411..417 and every
             * level beneath. This is the standard's own aggregation rule -- it adopted
             * decimal numbering precisely so data would "تجميع تلقائياً وفق تبويبات
             * الدليل" -- so the report does the same thing the chart was designed for.
             */
            $table->jsonb('account_codes')->nullable();
            $table->text('formula')->nullable();
            $table->smallInteger('sign')->default(1);
            // The كشف رقم cross-reference column the primary statements carry.
            $table->unsignedSmallInteger('analytical_ref')->nullable();
            $table->unsignedSmallInteger('indent_level')->default(0);
            $table->boolean('is_bold')->default(false);
            $table->timestampsTz();

            $table->unique(['statement_definition_id', 'sequence']);
        });

        DB::statement("ALTER TABLE statement_lines ADD CONSTRAINT stmt_line_type_valid CHECK (line_type IN ('header','accounts','formula','subtotal','total','note','spacer'))");
        DB::statement('ALTER TABLE statement_lines ADD CONSTRAINT stmt_line_sign_valid CHECK (sign IN (-1, 1))');

        foreach (['statement_definitions', 'statement_lines'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_lines');
        Schema::dropIfExists('statement_definitions');
    }
};
