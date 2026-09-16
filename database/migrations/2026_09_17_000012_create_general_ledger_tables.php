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
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('journal_id')->constrained();
            $table->foreignId('fiscal_period_id')->constrained();

            // Nullable because numbering happens at POST, not at draft: allocating a
            // number to a draft leaves a gap whenever the draft is abandoned, and gaps
            // are exactly what the sequence-gap report must be able to treat as a red
            // flag. Uniqueness is enforced by a partial index below.
            $table->string('entry_no', 30)->nullable();
            $table->date('entry_date');
            $table->date('posting_date');
            $table->text('description');
            $table->text('description_ar')->nullable();
            $table->char('currency_code', 3)->default('IQD');
            $table->decimal('exchange_rate', 20, 10)->default(1);

            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_document_no', 60)->nullable();
            $table->date('source_document_date')->nullable();

            $table->string('status', 20)->default('draft');
            $table->boolean('is_adjusting')->default(false);
            $table->boolean('is_closing')->default(false);
            $table->boolean('is_opening')->default(false);
            // System-generated entries (depreciation, allocation, closing, auto
            // reversal) have no human maker, so they are exempt from approval and
            // leave approved_by null rather than tripping the SoD check.
            $table->boolean('is_system_generated')->default(false);

            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries');
            $table->string('reversal_reason', 40)->nullable();
            $table->date('auto_reverse_on')->nullable();

            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestampTz('posted_at')->nullable();

            // Tamper evidence. Chained per (entity, journal, fiscal year) -- the same
            // grain as the gapless sequence counter, so it is already serialised by
            // the lock the sequencer holds and cannot fork under concurrency.
            $table->char('entry_hash', 64)->nullable();
            $table->char('prev_entry_hash', 64)->nullable();

            $table->timestampsTz();

            $table->index(['entity_id', 'fiscal_period_id', 'status']);
            $table->index(['entity_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        DB::statement('CREATE UNIQUE INDEX journal_entries_number_unique ON journal_entries (entity_id, journal_id, entry_no) WHERE entry_no IS NOT NULL');
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT je_status_valid CHECK (status IN ('draft','pending_approval','approved','posted','reversed','rejected'))");
        // Conditional, not absolute: a half-entered draft must be storable, and
        // PostgreSQL cannot defer a CHECK.
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT je_balanced_when_posted CHECK (status <> 'posted' OR total_debit = total_credit)");
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT je_posted_has_timestamp CHECK (status <> 'posted' OR (posted_at IS NOT NULL AND entry_no IS NOT NULL))");
        // Segregation of duties, at the database rather than by convention.
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT je_approver_not_creator CHECK (approved_by IS NULL OR approved_by <> created_by)');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT je_totals_non_negative CHECK (total_debit >= 0 AND total_credit >= 0)');

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            // Denormalised so reporting never has to join back to the header.
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('fiscal_period_id')->constrained();
            $table->date('entry_date');
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained();

            $table->foreignId('cost_centre_id')->nullable()->constrained();
            $table->foreignId('project_id')->nullable()->constrained();

            $table->char('currency_code', 3)->default('IQD');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            // Kept under IQD-only and asserted equal to the transaction amounts, so
            // reports that read the functional columns cannot silently return zero.
            $table->decimal('functional_debit', 20, 4)->default(0);
            $table->decimal('functional_credit', 20, 4)->default(0);

            $table->text('description')->nullable();
            $table->string('partner_type', 20)->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreignId('tax_code_id')->nullable()->constrained();
            $table->decimal('tax_base_amount', 20, 4)->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('uom', 20)->nullable();
            $table->unsignedBigInteger('reconciliation_id')->nullable();
            $table->timestampTz('posted_at')->nullable();

            $table->unique(['journal_entry_id', 'line_no']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        // Partition-ready primary key. Eloquent cannot address a composite key, so the
        // standalone unique on id stays as the key the ORM and child FKs use.
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT journal_lines_pkey');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_pkey PRIMARY KEY (id, entry_date)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_id_unique UNIQUE (id)');

        // Amount rules apply to posted lines only; a draft line may legitimately be
        // blank while it is being typed.
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT jl_amounts_non_negative CHECK (debit_amount >= 0 AND credit_amount >= 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT jl_one_side_only CHECK (posted_at IS NULL OR debit_amount = 0 OR credit_amount = 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT jl_not_empty CHECK (posted_at IS NULL OR debit_amount > 0 OR credit_amount > 0)');
        // IQD has no minor unit; without this, fractional residue from tax
        // calculations leaks in and never comes out.
        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT jl_iqd_is_integral CHECK (currency_code <> 'IQD' OR (debit_amount = round(debit_amount) AND credit_amount = round(credit_amount)))");

        // Reporting indexes. Laravel's grammar emits neither WHERE nor INCLUDE, so
        // these are raw.
        DB::statement('CREATE INDEX jl_report_idx ON journal_lines (entity_id, account_id, cost_centre_id, entry_date) INCLUDE (debit_amount, credit_amount) WHERE posted_at IS NOT NULL');
        DB::statement('CREATE INDEX jl_cost_centre_idx ON journal_lines (entity_id, cost_centre_id, entry_date) INCLUDE (account_id, debit_amount, credit_amount) WHERE posted_at IS NOT NULL');
        DB::statement('CREATE INDEX jl_entry_idx ON journal_lines (journal_entry_id)');
        DB::statement('CREATE INDEX jl_partner_idx ON journal_lines (partner_type, partner_id) WHERE partner_id IS NOT NULL');
        // Ledger data is naturally clustered by date, which is the rare case where
        // BRIN beats b-tree at a fraction of the size.
        DB::statement('CREATE INDEX jl_date_brin ON journal_lines USING brin (entry_date)');

        Schema::create('journal_line_dimensions', function (Blueprint $table): void {
            $table->unsignedBigInteger('journal_line_id');
            $table->foreignId('dimension_id')->constrained();
            $table->foreignId('dimension_member_id')->constrained();

            $table->primary(['journal_line_id', 'dimension_id']);
            $table->foreign('journal_line_id')->references('id')->on('journal_lines');
        });

        foreach (['journal_entries', 'journal_lines'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_line_dimensions');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
