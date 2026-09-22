<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posting rules as configuration (Document B §2.4, Document C tabs 12-13).
     *
     * A rule is a row, not a branch in code. It is effective-dated so that re-running
     * a historical report never applies today's rule to yesterday's data. The raw
     * selector text is kept beside its normalised form, so the rule can always be
     * read against the document it came from.
     */
    public function up(): void
    {
        Schema::create('transaction_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();                  // TT-01
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->text('business_event')->nullable();
            $table->text('debit_logic')->nullable();
            $table->text('credit_logic')->nullable();
            $table->text('required_dimensions')->nullable();
            $table->text('required_documents')->nullable();
            $table->text('approval_path')->nullable();
            $table->text('reports_affected')->nullable();
            $table->text('control_rules')->nullable();
            // Derived from the approval path: whether a Senior Accountant review
            // precedes approval, and whether a board reference is required.
            $table->boolean('requires_review')->default(true);
            $table->boolean('requires_board_approval')->default(false);
            // TT-35 reversal and TT-39 migration have engine paths of their own and
            // are never chosen on the entry screen.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('posting_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();                  // PR-01
            $table->foreignId('transaction_type_id')->constrained();
            $table->text('condition');
            $table->text('debit_selector_raw');
            $table->text('credit_selector_raw');
            $table->text('debit_selector');
            $table->text('credit_selector');
            $table->jsonb('mandatory_dimensions');
            $table->jsonb('blocking_rules');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('approval_status', 10)->default('approved');
            $table->string('source', 200)->nullable();
            $table->timestampsTz();

            $table->index(['transaction_type_id', 'effective_from']);
        });

        DB::statement("ALTER TABLE posting_rules ADD CONSTRAINT posting_rules_approval_valid CHECK (approval_status IN ('approved','pending'))");
        DB::statement('ALTER TABLE posting_rules ADD CONSTRAINT posting_rules_range_valid CHECK (effective_to IS NULL OR effective_to > effective_from)');

        foreach (['transaction_types', 'posting_rules'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_rules');
        Schema::dropIfExists('transaction_types');
    }
};
