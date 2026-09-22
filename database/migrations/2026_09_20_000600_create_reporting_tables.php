<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reporting engine's configuration (M07). Statement lines (Document C tab 14)
     * and the account mapping (tab 15) are the only source of statement figures:
     * no report class carries an account list (VR-55). Both are effective-dated, so a
     * report re-run for a past date applies the mapping in force then.
     */
    public function up(): void
    {
        Schema::create('fs_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();                 // SFP-A-010
            $table->string('statement', 4);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('line_type', 10);
            $table->text('account_selector')->nullable();
            $table->smallInteger('sign');
            $table->unsignedSmallInteger('display_order');
            $table->string('subtotal_of', 20)->nullable();
            $table->string('computed_from', 20)->nullable();      // SFP-E-250 <- PL-900
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE fs_lines ADD CONSTRAINT fs_lines_statement_valid CHECK (statement IN ('SFP','PL','CF','SCE'))");
        DB::statement("ALTER TABLE fs_lines ADD CONSTRAINT fs_lines_type_valid CHECK (line_type IN ('accounts','subtotal','computed','control'))");
        DB::statement('ALTER TABLE fs_lines ADD CONSTRAINT fs_lines_sign_valid CHECK (sign IN (1, -1))');

        Schema::create('report_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->string('fs_line_code', 20);
            $table->string('statement', 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('approval_ref', 120)->nullable();
            $table->timestampsTz();

            $table->foreign('fs_line_code')->references('code')->on('fs_lines');
            $table->index(['statement', 'account_id']);
        });

        // One effective line per account per statement at any date (acceptance
        // criterion 3: every posting account resolves to exactly one line).
        DB::statement(<<<'SQL'
            ALTER TABLE report_mappings ADD CONSTRAINT report_mappings_one_line EXCLUDE USING gist (
                account_id WITH =,
                statement WITH =,
                daterange(effective_from, effective_to, '[)') WITH &&
            )
        SQL);

        Schema::create('report_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();                  // RPT-04
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('module', 60)->nullable();
            $table->text('content')->nullable();
            $table->text('filters')->nullable();
            $table->text('source_entities')->nullable();
            $table->string('export', 60)->nullable();
            $table->string('phase', 10);
            $table->timestampsTz();
        });

        Schema::create('report_filters', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();                  // F-01
            $table->string('name', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('filter_type', 20);
            $table->text('applies_to')->nullable();
            $table->string('bound_field', 80);
            $table->timestampsTz();
        });

        foreach (['fs_lines', 'report_mappings', 'report_definitions', 'report_filters'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('report_mappings');
        Schema::dropIfExists('fs_lines');
    }
};
