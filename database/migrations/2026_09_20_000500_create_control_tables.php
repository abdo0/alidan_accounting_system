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
     * Controls (M09): duplicate flags (Document B §2.5) and the exceptions register
     * (§4.7). Neither is ever deleted -- a flag's disposition and an exception's
     * resolution are the record (VR-59).
     */
    public function up(): void
    {
        Schema::create('duplicate_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_header_id')->constrained()->restrictOnDelete();
            $table->foreignId('matched_journal_id')->nullable()->constrained('journal_headers')->restrictOnDelete();
            $table->string('flag_type', 20);
            $table->text('match_reason');
            $table->unsignedSmallInteger('score');
            $table->decimal('value_at_risk', 21, 0);
            $table->string('disposition', 30)->nullable();
            $table->foreignId('dispositioned_by')->nullable()->constrained('users');
            $table->timestampTz('dispositioned_at')->nullable();
            $table->text('disposition_note')->nullable();
            // POSSIBLE AMER DUPLICATE, REQUIRES MANUAL REVIEW ... for flags carried
            // in from the authoritative workbook (MIG-14).
            $table->string('source_review', 60)->nullable();
            $table->string('origin', 12)->default('service');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['journal_header_id', 'disposition']);
        });

        IqdColumns::apply('duplicate_flags', ['value_at_risk' => 'amount']);
        DB::statement("ALTER TABLE duplicate_flags ADD CONSTRAINT duplicate_flags_type_valid CHECK (flag_type IN ('exact','source_reference','near_date','source_carried'))");
        DB::statement("ALTER TABLE duplicate_flags ADD CONSTRAINT duplicate_flags_disposition_valid CHECK (disposition IS NULL OR disposition IN ('confirmed_duplicate','not_a_duplicate','manual_review_required','held_unposted'))");
        DB::statement("ALTER TABLE duplicate_flags ADD CONSTRAINT duplicate_flags_origin_valid CHECK (origin IN ('service','migration'))");
        DB::statement('ALTER TABLE duplicate_flags ADD CONSTRAINT duplicate_flags_disposition_has_actor CHECK (disposition IS NULL OR dispositioned_at IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX duplicate_flags_once ON duplicate_flags (journal_header_id, coalesce(matched_journal_id, 0), flag_type)');

        // A flag is never deleted, and a disposition is never withdrawn.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION duplicate_flag_guard()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'A duplicate flag is never deleted; its disposition is the record'
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF OLD.disposition IS NOT NULL AND NEW.disposition IS NULL THEN
                    RAISE EXCEPTION 'A disposition cannot be withdrawn'
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER duplicate_flags_guard BEFORE UPDATE OR DELETE ON duplicate_flags
                FOR EACH ROW EXECUTE FUNCTION duplicate_flag_guard()
        SQL);

        Schema::create('exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('exception_no', 20)->unique();
            // EXC-OPEN-01 ... for exceptions carried from the authoritative workbook.
            $table->string('source_code', 30)->nullable()->unique();
            $table->string('category', 40);
            $table->date('raised_date');
            $table->text('subject');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('amount', 21, 0)->nullable();
            $table->string('volume', 120)->nullable();
            $table->string('status', 15)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('raised_by')->nullable()->constrained('users');
            $table->text('required_action')->nullable();
            $table->text('proposed_resolution')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('journal_header_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained();
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->foreignId('fiscal_year_id')->nullable()->constrained();
            // EXC-SYS-04: the 2026 source is incomplete, so its periods may not be
            // finally closed while this is open.
            $table->boolean('blocks_final_close')->default(false);
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->timestampsTz();

            $table->index(['status', 'category']);
        });

        IqdColumns::apply('exceptions', ['amount' => 'signed']);
        DB::statement("ALTER TABLE exceptions ADD CONSTRAINT exceptions_status_valid CHECK (status IN ('open','under_review','resolved'))");
        DB::statement(<<<'SQL'
            ALTER TABLE exceptions ADD CONSTRAINT exceptions_category_valid CHECK (category IN (
                'open_control_difference', 'stage_difference', 'unidentified_receipt', 'probable_duplicate',
                'potential_duplicates', 'suspense_item', 'review_required', 'classification_review',
                'unpriced_source_line', 'undetermined_reclassification', 'amount_under_review',
                'contractor_account_open', 'undated_entries', 'inconsistent_dates', 'anonymised_payees',
                'pending_evidence', 'reference_data_gap', 'missing_dimension', 'source_incomplete',
                'contract_data_missing', 'missing_document', 'partial_document', 'amount_difference',
                'overdue_advance', 'cash_variance', 'source_exception'
            ))
        SQL);
        // VR-59: resolved only with a resolution and a resolver.
        DB::statement(<<<'SQL'
            ALTER TABLE exceptions ADD CONSTRAINT ck_resolved_has_resolution CHECK (
                status <> 'resolved'
                OR (length(trim(coalesce(resolution, ''))) > 0 AND resolved_by IS NOT NULL AND resolved_at IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER exceptions_no_delete BEFORE DELETE ON exceptions
                FOR EACH ROW EXECUTE FUNCTION refuse_change('VR-59: an exception is never deleted')
        SQL);

        Schema::create('exception_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exception_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('comment');
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER exception_comments_immutable BEFORE UPDATE OR DELETE ON exception_comments
                FOR EACH ROW EXECUTE FUNCTION refuse_change('comments are permanent')
        SQL);

        foreach (['duplicate_flags', 'exceptions', 'exception_comments'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_comments');
        Schema::dropIfExists('exceptions');
        Schema::dropIfExists('duplicate_flags');
        DB::statement('DROP FUNCTION IF EXISTS duplicate_flag_guard()');
    }
};
