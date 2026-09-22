<?php

declare(strict_types=1);

use App\Support\Database\IqdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parameters (M00), the funding dimensions (M02, M05), reference value lists and
     * master-data change control (VR-60, RE-11).
     */
    public function up(): void
    {
        // Effective-dated with full history (Document C tab 27). A change is always
        // a new row: the old one is closed by setting effective_to, and that is the
        // only update the table accepts. The commercial operation date is a row
        // with a NULL value -- the system must never infer one (VR-39).
        Schema::create('parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('param_code', 60);
            $table->string('spec_ref', 20)->nullable();          // PARAM-007
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('data_type', 10);
            $table->text('value')->nullable();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('approval_ref', 120)->nullable();
            $table->text('reason')->nullable();
            $table->string('source', 200)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['company_id', 'param_code', 'effective_from']);
        });

        DB::statement("ALTER TABLE parameters ADD CONSTRAINT parameters_type_valid CHECK (data_type IN ('string','integer','decimal','date','boolean'))");
        DB::statement('ALTER TABLE parameters ADD CONSTRAINT parameters_range_valid CHECK (effective_to IS NULL OR effective_to > effective_from)');
        DB::statement(<<<'SQL'
            ALTER TABLE parameters ADD CONSTRAINT parameters_no_overlap EXCLUDE USING gist (
                company_id WITH =,
                param_code WITH =,
                (coalesce(project_id, 0)) WITH =,
                daterange(effective_from, effective_to, '[)') WITH &&
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION parameters_history_guard()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Parameter history is never deleted'
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                -- The only permitted change: closing an open row.
                IF OLD.effective_to IS NULL AND NEW.effective_to IS NOT NULL
                   AND (to_jsonb(NEW) - 'effective_to') = (to_jsonb(OLD) - 'effective_to')
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'A parameter value is changed by adding a new effective-dated row'
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER parameters_history_guard BEFORE UPDATE OR DELETE ON parameters
                FOR EACH ROW EXECUTE FUNCTION parameters_history_guard()
        SQL);

        Schema::create('funding_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('source_type', 20);
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE funding_sources ADD CONSTRAINT funding_sources_type_valid CHECK (source_type IN ('shareholder','third_party','recovery','recycled_recovery'))");

        Schema::create('funding_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('source_of_value', 200)->nullable();
            // "Journal only" values are loaded but flagged for formal approval
            // (CONF-10, EXC-SYS-01).
            $table->string('approval_status', 10)->default('approved');
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('funding_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('batch_ref', 60);                 // AMER-512, DF-161, ...
            $table->string('series', 20)->nullable();        // AMER, ABS, ABA, DF, TAJ-DAR, ACC-JV
            $table->foreignId('funding_source_id')->nullable()->constrained();
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->date('batch_date')->nullable();
            // As the source states it; never a balance (Document B §1.1).
            $table->decimal('source_amount', 21, 0)->nullable();
            $table->text('actual_payment_source')->nullable();
            $table->string('source_file', 200)->nullable();
            $table->string('source_row', 30)->nullable();
            $table->string('status', 16)->default('posted');
            $table->timestampsTz();

            $table->unique(['company_id', 'batch_ref']);
        });

        IqdColumns::apply('funding_batches', ['source_amount' => 'amount']);
        DB::statement("ALTER TABLE funding_batches ADD CONSTRAINT funding_batches_status_valid CHECK (status IN ('posted','held','review_required','excluded'))");

        // The twelve funding-chain steps (Document C tab 10) and the account pair
        // each one permits (Document B §2.6).
        Schema::create('chain_steps', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('label', 200);
            $table->string('label_ar', 200)->nullable();
            $table->text('debit_selector');
            $table->text('credit_selector');
            $table->unsignedSmallInteger('sort_order');
            $table->timestampsTz();
        });

        Schema::create('value_list_items', function (Blueprint $table): void {
            $table->id();
            $table->string('list_code', 40);
            $table->string('code', 20)->nullable();
            $table->string('value', 300);
            $table->string('value_ar', 300)->nullable();
            $table->string('source_of_value', 200)->nullable();
            $table->string('approval_status', 10)->default('approved');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['list_code', 'value']);
        });

        foreach (['funding_categories', 'value_list_items'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_approval_valid CHECK (approval_status IN ('approved','pending'))");
        }

        // Maker-checker on the chart, parameters, mappings and master data. A change
        // is proposed, decided by someone else, then applied under an audit context
        // so the audit trail carries the old and new values with the reason.
        Schema::create('change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('object_type', 60);
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('action', 12);
            $table->jsonb('payload');
            $table->jsonb('previous')->nullable();
            $table->text('reason');
            $table->string('approval_ref', 120)->nullable();
            $table->string('status', 10)->default('proposed');
            $table->foreignId('proposed_by')->nullable()->constrained('users');
            $table->timestampTz('proposed_at')->useCurrent();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();

            $table->index(['object_type', 'object_id']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE change_requests ADD CONSTRAINT change_requests_status_valid CHECK (status IN ('proposed','approved','rejected','applied'))");
        DB::statement("ALTER TABLE change_requests ADD CONSTRAINT change_requests_action_valid CHECK (action IN ('create','update','deactivate'))");
        DB::statement('ALTER TABLE change_requests ADD CONSTRAINT change_requests_checker_not_maker CHECK (decided_by IS NULL OR proposed_by IS NULL OR decided_by <> proposed_by)');
        DB::statement('ALTER TABLE change_requests ADD CONSTRAINT change_requests_reason_given CHECK (length(trim(reason)) > 0)');

        foreach ([
            'parameters', 'funding_sources', 'funding_categories', 'funding_batches',
            'chain_steps', 'value_list_items', 'change_requests',
        ] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
        Schema::dropIfExists('value_list_items');
        Schema::dropIfExists('chain_steps');
        Schema::dropIfExists('funding_batches');
        Schema::dropIfExists('funding_categories');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('parameters');
        DB::statement('DROP FUNCTION IF EXISTS parameters_history_guard()');
    }
};
