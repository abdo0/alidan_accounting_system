<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounts');
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('account_class', 20);
            $table->string('account_subtype', 40)->nullable();
            $table->char('normal_balance', 1);
            $table->string('statement', 10);
            $table->string('cash_flow_class', 20)->nullable();
            $table->foreignId('contra_of_account_id')->nullable()->constrained('accounts');

            // Only leaves take postings; parents exist for rollup.
            $table->boolean('is_postable')->default(true);
            // A control account is written only by its subledger, never by hand.
            $table->boolean('is_control_account')->default(false);
            $table->string('control_subledger', 20)->nullable();
            $table->boolean('allow_manual_entry')->default(true);
            $table->boolean('is_reconcilable')->default(false);

            // Cost centre policy (docs/05 §5.4). enforced_from makes enforcement
            // date-based, so a back-dated correction into a pre-enforcement period
            // still posts while current entries are held to the new standard.
            $table->boolean('requires_cost_centre')->default(false);
            $table->date('cost_centre_enforced_from')->nullable();
            $table->unsignedBigInteger('default_cost_centre_id')->nullable();
            $table->boolean('requires_project')->default(false);

            $table->char('currency_code', 3)->nullable();
            // Mapping to the statutory chart, kept beside the operational code rather
            // than contorting the operational chart into a statutory layout.
            $table->string('statutory_code', 30)->nullable();
            $table->unsignedInteger('display_order')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['account_class', 'code']);
            $table->index('parent_id');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_normal_balance_valid CHECK (normal_balance IN ('D','C'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_statement_valid CHECK (statement IN ('BS','PL','SOCE','NONE'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_class_valid CHECK (account_class IN ('asset','liability','equity','revenue','cost_of_sales','expense','other_income','other_expense','tax','clearing','statistical'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_cash_flow_valid CHECK (cash_flow_class IS NULL OR cash_flow_class IN ('operating','investing','financing','none'))");
        // A control account that also accepts manual entry is how a subledger stops
        // reconciling to its control account.
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_control_not_manual CHECK (NOT (is_control_account AND allow_manual_entry))');
        // A heading cannot be a control account for anything.
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_control_is_postable CHECK (NOT is_control_account OR is_postable)');

        // Master data is never deleted -- an account carrying history cannot be
        // recovered, and codes are never reused.
        DB::statement('CREATE RULE accounts_no_delete AS ON DELETE TO accounts DO INSTEAD NOTHING');

        // Which accounts a given entity may use. V-05 checks this, so the chart
        // seeder must populate it or every post is blocked on day one.
        Schema::create('entity_account_settings', function (Blueprint $table): void {
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->primary(['entity_id', 'account_id']);
        });

        foreach (['accounts', 'entity_account_settings'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_account_settings');
        DB::statement('DROP RULE IF EXISTS accounts_no_delete ON accounts');
        Schema::dropIfExists('accounts');
    }
};
