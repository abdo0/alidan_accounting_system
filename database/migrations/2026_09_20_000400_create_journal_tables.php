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
     * The journal (M03, Document B §3.2).
     *
     * journal_lines is the only financial fact table in the system. Every balance,
     * subledger, statement and KPI is an aggregation over it (Document B §1.1); no
     * header total, balance or derived figure is stored anywhere else.
     *
     * The storage layer enforces what no application defect may break:
     *   ck_one_side      a line is a debit or a credit, never both, never zero (VR-03)
     *   iqd_amount       whole dinars only (VR-19)
     *   ck_undated       an entry may be undated only if its source carried no date
     *   jh_balanced      Σ debit = Σ credit once an entry leaves Draft (VR-01)
     *   jh_immutable     a posted entry is never edited or deleted (VR-18)
     */
    public function up(): void
    {
        Schema::create('journal_headers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('period_id')->constrained('accounting_periods');
            // Assigned at posting from a gapless sequence (VR-10), so NULL before.
            $table->string('jv_no', 30)->nullable();
            $table->string('pv_no', 30)->nullable();
            $table->string('rv_no', 30)->nullable();
            $table->foreignId('transaction_type_id')->constrained();
            $table->foreignId('posting_rule_id')->nullable()->constrained();
            $table->date('txn_date')->nullable();
            $table->date('posting_date');
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->string('date_status', 20)->default('ok');
            $table->string('doc_status', 10);
            $table->string('doc_ref', 120)->nullable();
            $table->string('recon_status', 60)->nullable();
            $table->string('source_presence', 200)->nullable();
            $table->string('source_reference', 120)->nullable();
            $table->string('source_file', 200)->nullable();
            $table->string('source_row', 30)->nullable();
            $table->string('status', 10)->default('draft');
            $table->boolean('is_migration')->default(false);
            $table->unsignedBigInteger('migration_run_id')->nullable();
            $table->foreignId('reversal_of_journal_id')->nullable()->constrained('journal_headers');
            $table->foreignId('reversed_by_journal_id')->nullable()->constrained('journal_headers');
            $table->foreignId('linked_journal_id')->nullable()->constrained('journal_headers');
            $table->string('approval_ref', 120)->nullable();
            $table->string('resolution_ref', 120)->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('soft_close_reason')->nullable();
            $table->jsonb('acknowledged_warnings')->nullable();
            $table->text('rejection_comment')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestampTz('posted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestampTz('rejected_at')->nullable();
            $table->char('entry_hash', 64)->nullable();
            $table->char('prev_entry_hash', 64)->nullable();
            $table->string('idempotency_key', 160)->nullable()->unique();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT jh_status_valid CHECK (status IN ('draft','submitted','reviewed','approved','posted','reversed'))");
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT jh_doc_status_valid CHECK (doc_status IN ('complete','partial','missing'))");
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT jh_date_status_valid CHECK (date_status IN ('ok','no_date_in_source','missing','inconsistent'))");
        DB::statement('ALTER TABLE journal_headers ADD CONSTRAINT ck_dates CHECK (txn_date IS NULL OR txn_date <= posting_date)');
        // Document B §3.2: the database-level anti-fabrication control.
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT ck_undated CHECK (txn_date IS NOT NULL OR date_status <> 'ok')");
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT ck_posted_has_jv CHECK (status NOT IN ('posted','reversed') OR (jv_no IS NOT NULL AND posted_at IS NOT NULL))");
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT ck_doc_ref_when_complete CHECK (doc_status <> 'complete' OR doc_ref IS NOT NULL)");
        DB::statement('ALTER TABLE journal_headers ADD CONSTRAINT ck_reviewer_not_creator CHECK (reviewed_by IS NULL OR reviewed_by <> created_by OR is_migration)');
        DB::statement('ALTER TABLE journal_headers ADD CONSTRAINT ck_approver_not_creator CHECK (approved_by IS NULL OR approved_by <> created_by OR is_migration)');
        DB::statement('ALTER TABLE journal_headers ADD CONSTRAINT ck_migration_source CHECK (NOT is_migration OR (source_file IS NOT NULL AND source_row IS NOT NULL))');
        DB::statement("ALTER TABLE journal_headers ADD CONSTRAINT ck_reversal_reason CHECK (reversal_of_journal_id IS NULL OR length(trim(coalesce(reversal_reason, ''))) > 0)");
        DB::statement('CREATE UNIQUE INDEX uq_jv ON journal_headers (company_id, jv_no) WHERE jv_no IS NOT NULL');
        DB::statement('CREATE INDEX ix_jh_period_status ON journal_headers (period_id, status)');
        DB::statement('CREATE INDEX ix_jh_company_posting ON journal_headers (company_id, status, posting_date)');
        DB::statement('CREATE INDEX ix_jh_source_ref ON journal_headers (source_reference) WHERE source_reference IS NOT NULL');
        DB::statement('CREATE INDEX ix_jh_created_by ON journal_headers (created_by, status)');

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_header_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 21, 0)->default(0);
            $table->decimal('credit', 21, 0)->default(0);
            $table->foreignId('project_id')->constrained();
            $table->foreignId('cost_center_id')->nullable()->constrained();
            // EXC-SYS-03: two source rows carry no cost centre. Only the importer may
            // record that, and only with the exception that explains it.
            $table->string('vr05_exemption_ref', 60)->nullable();
            $table->foreignId('resp_center_id')->nullable()->constrained('responsibility_centers');
            $table->boolean('rc_derived')->default(false);
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->foreignId('advance_holder_id')->nullable()->constrained('counterparties');
            $table->foreignId('cash_account_id')->nullable()->constrained('bank_accounts');
            $table->foreignId('funding_source_id')->nullable()->constrained();
            $table->foreignId('funding_batch_id')->nullable()->constrained();
            $table->foreignId('funding_category_id')->nullable()->constrained();
            $table->foreignId('contract_id')->nullable()->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained();
            $table->foreignId('chain_step_id')->nullable()->constrained();
            $table->unsignedBigInteger('advance_id')->nullable();
            // Captured on the advance line when an advance is issued (PR-07, RE-13).
            $table->date('settlement_deadline')->nullable();
            $table->string('capex_opex', 5)->nullable();
            $table->string('asset_class', 40)->nullable();
            $table->string('handover_req', 40)->nullable();
            $table->boolean('revenue_eligible')->nullable();
            $table->text('eligibility_reason')->nullable();
            $table->string('recon_status', 60)->nullable();
            $table->decimal('source_amount', 21, 0)->nullable();
            $table->decimal('amount_difference', 21, 0)->nullable();
            $table->text('actual_payment_source')->nullable();
            $table->text('actual_receiver')->nullable();
            $table->string('duplicate_review', 60)->nullable();
            $table->string('date_comparison', 60)->nullable();
            $table->string('source_match_ref', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['journal_header_id', 'line_no'], 'uq_line');
        });

        IqdColumns::apply('journal_lines', [
            'debit' => 'amount',
            'credit' => 'amount',
            'source_amount' => 'amount',
            'amount_difference' => 'signed',
        ]);

        DB::statement('ALTER TABLE journal_lines ALTER COLUMN debit SET DEFAULT 0');
        DB::statement('ALTER TABLE journal_lines ALTER COLUMN credit SET DEFAULT 0');
        DB::statement(<<<'SQL'
            ALTER TABLE journal_lines ADD CONSTRAINT ck_one_side CHECK (
                debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0) AND (debit + credit) > 0
            )
        SQL);
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT ck_cc_or_exemption CHECK (cost_center_id IS NOT NULL OR vr05_exemption_ref IS NOT NULL)');
        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_capex_opex_valid CHECK (capex_opex IS NULL OR capex_opex IN ('capex','opex','na'))");

        // Document B §3.2 indexes, sized for aggregation over posted lines.
        DB::statement('CREATE INDEX ix_jl_account ON journal_lines (account_id) INCLUDE (debit, credit, project_id, cost_center_id, journal_header_id)');
        DB::statement('CREATE INDEX ix_jl_header ON journal_lines (journal_header_id)');
        DB::statement('CREATE INDEX ix_jl_project ON journal_lines (project_id, account_id)');
        DB::statement('CREATE INDEX ix_jl_counterparty ON journal_lines (counterparty_id) WHERE counterparty_id IS NOT NULL');
        DB::statement('CREATE INDEX ix_jl_advance ON journal_lines (advance_holder_id, account_id) WHERE advance_holder_id IS NOT NULL');
        DB::statement('CREATE INDEX ix_jl_cash ON journal_lines (cash_account_id) WHERE cash_account_id IS NOT NULL');
        DB::statement('CREATE INDEX ix_jl_batch ON journal_lines (funding_batch_id) WHERE funding_batch_id IS NOT NULL');

        $this->createIntegrityTriggers();

        foreach (['journal_headers', 'journal_lines'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    private function createIntegrityTriggers(): void
    {
        // VR-01 / VR-02 at the database. Deferred to commit, because the header and
        // its lines are written by separate statements.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_assert_balanced(p_header_id bigint)
            RETURNS void AS $$
            DECLARE
                v_status text;
                v_debit  numeric;
                v_credit numeric;
                v_dr     integer;
                v_cr     integer;
            BEGIN
                SELECT status INTO v_status FROM journal_headers WHERE id = p_header_id;
                IF v_status IS NULL OR v_status = 'draft' THEN
                    RETURN;
                END IF;

                SELECT coalesce(sum(debit), 0), coalesce(sum(credit), 0),
                       count(*) FILTER (WHERE debit > 0), count(*) FILTER (WHERE credit > 0)
                  INTO v_debit, v_credit, v_dr, v_cr
                  FROM journal_lines WHERE journal_header_id = p_header_id;

                IF v_debit <> v_credit THEN
                    RAISE EXCEPTION 'VR-01: journal % is not balanced (debit %, credit %)', p_header_id, v_debit, v_credit
                        USING ERRCODE = 'check_violation';
                END IF;

                IF v_dr = 0 OR v_cr = 0 THEN
                    RAISE EXCEPTION 'VR-02: journal % needs at least one debit and one credit line', p_header_id
                        USING ERRCODE = 'check_violation';
                END IF;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_header_balance_trigger()
            RETURNS trigger AS $$
            BEGIN
                PERFORM journal_assert_balanced(NEW.id);
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_line_balance_trigger()
            RETURNS trigger AS $$
            BEGIN
                PERFORM journal_assert_balanced(coalesce(NEW.journal_header_id, OLD.journal_header_id));
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER jh_balanced AFTER INSERT OR UPDATE ON journal_headers
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION journal_header_balance_trigger()
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER jl_balanced AFTER INSERT OR UPDATE OR DELETE ON journal_lines
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION journal_line_balance_trigger()
        SQL);

        // VR-18 / VR-51. A posted entry changes only by being reversed, or by the
        // controlled evidence corrections a service opens explicitly: a document
        // arriving (Missing -> Partial -> Complete) and a date the source lacked
        // being supplied. Everything else -- amount, account, description, source
        // reference -- is refused for every principal.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_header_guard()
            RETURNS trigger AS $$
            DECLARE
                v_allowed text[] := ARRAY['updated_at'];
                v_rank    jsonb := '{"missing":0,"partial":1,"complete":2}';
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status <> 'draft' THEN
                        RAISE EXCEPTION 'VR-18: journal % is % and cannot be deleted', OLD.id, OLD.status
                            USING ERRCODE = 'insufficient_privilege';
                    END IF;
                    RETURN OLD;
                END IF;

                IF OLD.status NOT IN ('posted', 'reversed') THEN
                    RETURN NEW;
                END IF;

                IF OLD.status = 'posted' AND NEW.status = 'reversed' THEN
                    v_allowed := v_allowed || ARRAY['status', 'reversed_by_journal_id'];
                END IF;

                IF coalesce(current_setting('app.controlled_correction', true), '') = '1' THEN
                    IF (v_rank ->> NEW.doc_status)::int < (v_rank ->> OLD.doc_status)::int THEN
                        RAISE EXCEPTION 'Document status of a posted entry may only improve'
                            USING ERRCODE = 'insufficient_privilege';
                    END IF;
                    v_allowed := v_allowed || ARRAY['doc_status', 'doc_ref'];
                    IF OLD.txn_date IS NULL THEN
                        v_allowed := v_allowed || ARRAY['txn_date', 'date_status'];
                    END IF;
                END IF;

                IF (to_jsonb(NEW) - v_allowed) IS DISTINCT FROM (to_jsonb(OLD) - v_allowed) THEN
                    RAISE EXCEPTION 'VR-18: posted journal % cannot be edited; correct it by reversal or reclassification', OLD.id
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER jh_immutable BEFORE UPDATE OR DELETE ON journal_headers
                FOR EACH ROW EXECUTE FUNCTION journal_header_guard()
        SQL);

        // Lines are edited only while their header is a Draft.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_line_guard()
            RETURNS trigger AS $$
            DECLARE
                v_status text;
            BEGIN
                SELECT status INTO v_status FROM journal_headers
                 WHERE id = coalesce(NEW.journal_header_id, OLD.journal_header_id);

                -- The header itself is being deleted (a draft): cascade is fine.
                IF v_status IS NULL THEN
                    RETURN coalesce(NEW, OLD);
                END IF;

                IF v_status <> 'draft' THEN
                    RAISE EXCEPTION 'VR-18: lines of a % journal cannot be changed', v_status
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN coalesce(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER jl_editable_only_in_draft BEFORE INSERT OR UPDATE OR DELETE ON journal_lines
                FOR EACH ROW EXECUTE FUNCTION journal_line_guard()
        SQL);

        // VR-54: a migration entry may not be created after go-live.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_migration_cutoff_guard()
            RETURNS trigger AS $$
            DECLARE
                v_go_live date;
            BEGIN
                IF NOT NEW.is_migration THEN
                    RETURN NEW;
                END IF;

                SELECT nullif(value, '')::date INTO v_go_live
                  FROM parameters
                 WHERE company_id = NEW.company_id AND param_code = 'go_live_date'
                   AND project_id IS NULL AND effective_to IS NULL
                 LIMIT 1;

                IF v_go_live IS NOT NULL AND current_date >= v_go_live THEN
                    RAISE EXCEPTION 'VR-54: migration entries may not be created after go-live (%)', v_go_live
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER jh_no_migration_after_golive BEFORE INSERT ON journal_headers
                FOR EACH ROW EXECUTE FUNCTION journal_migration_cutoff_guard()
        SQL);

        // Document B §4.1: an account's code never changes once transactions exist.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION account_code_guard()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code
                   AND EXISTS (SELECT 1 FROM journal_lines WHERE account_id = OLD.id) THEN
                    RAISE EXCEPTION 'Account % has transactions; its code cannot change', OLD.code
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER accounts_code_immutable BEFORE UPDATE OF code ON accounts
                FOR EACH ROW EXECUTE FUNCTION account_code_guard()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS accounts_code_immutable ON accounts');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_headers');

        foreach ([
            'account_code_guard()', 'journal_migration_cutoff_guard()', 'journal_line_guard()',
            'journal_header_guard()', 'journal_line_balance_trigger()', 'journal_header_balance_trigger()',
            'journal_assert_balanced(bigint)',
        ] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}");
        }
    }
};
