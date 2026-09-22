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
     * Subledger records (M04 advances, M11 reconciliations) and the run register
     * behind VR-41.
     *
     * None of them stores a balance. An advance's outstanding amount is the issue
     * line less the settlement lines linked to it; a reconciliation's book balance
     * and variance are computed on read (VR-57). What these tables hold are links,
     * classifications and evidence.
     */
    public function up(): void
    {
        Schema::create('advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('advance_ref', 30)->unique();
            $table->foreignId('holder_id')->constrained('counterparties');
            $table->foreignId('account_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('cost_center_id')->nullable()->constrained();
            $table->foreignId('resp_center_id')->nullable()->constrained('responsibility_centers');
            $table->foreignId('issue_journal_id')->nullable()->constrained('journal_headers')->restrictOnDelete();
            $table->foreignId('issue_line_id')->nullable()->unique()->constrained('journal_lines')->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->date('issue_date');
            $table->date('settlement_deadline')->nullable();
            // A historic position the migration opens for a holder and account whose
            // source does not identify individual advances (MIG-17).
            $table->boolean('is_historic')->default(false);
            $table->timestampsTz();

            $table->index(['holder_id', 'account_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->foreign('advance_id')->references('id')->on('advances');
        });

        Schema::create('advance_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advance_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            // A claim awaiting evidence has no ledger line: it does not reduce the
            // advance, and it holds an open exception (Document B §4.3).
            $table->decimal('claimed_amount', 21, 0)->nullable();
            $table->string('settlement_type', 20);
            $table->string('classification', 30)->nullable();
            $table->string('evidence_status', 12)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        IqdColumns::apply('advance_settlements', ['claimed_amount' => 'amount']);
        DB::statement('ALTER TABLE advance_settlements ADD CONSTRAINT advance_settlements_line_or_claim CHECK ((journal_line_id IS NULL) <> (claimed_amount IS NULL))');
        DB::statement("ALTER TABLE advance_settlements ADD CONSTRAINT advance_settlements_type_valid CHECK (settlement_type IN ('capex','opex','fixed_asset','acquisition','refund','reclassification','sub_advance','shortfall','recovery','other'))");
        DB::statement("ALTER TABLE advance_settlements ADD CONSTRAINT advance_settlements_class_valid CHECK (classification IS NULL OR classification IN ('a_valid_prj01','b_valid_prj03','c_personal_receivable','d_pending_evidence'))");
        DB::statement("ALTER TABLE advance_settlements ADD CONSTRAINT advance_settlements_evidence_valid CHECK (evidence_status IS NULL OR evidence_status IN ('complete','partial','missing','pending'))");

        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('recon_type', 20);
            $table->foreignId('bank_account_id')->nullable()->constrained();
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->foreignId('account_id')->nullable()->constrained();
            $table->foreignId('period_id')->nullable()->constrained('accounting_periods');
            $table->date('as_at_date');
            // The only figure a reconciliation records is the one from outside the
            // ledger: the counted cash or the bank statement balance.
            $table->decimal('actual_balance', 21, 0);
            $table->foreignId('document_id')->nullable()->constrained();
            $table->foreignId('responsible_user_id')->constrained('users');
            $table->string('status', 12)->default('draft');
            $table->foreignId('prepared_by')->constrained('users');
            $table->timestampTz('prepared_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['bank_account_id', 'as_at_date']);
        });

        IqdColumns::apply('reconciliations', ['actual_balance' => 'signed']);
        DB::statement("ALTER TABLE reconciliations ADD CONSTRAINT reconciliations_type_valid CHECK (recon_type IN ('cash_count','bank','advance','contractor','supplier','government_share'))");
        DB::statement("ALTER TABLE reconciliations ADD CONSTRAINT reconciliations_status_valid CHECK (status IN ('draft','submitted','approved'))");
        DB::statement('ALTER TABLE reconciliations ADD CONSTRAINT reconciliations_checker_not_preparer CHECK (approved_by IS NULL OR approved_by <> prepared_by)');

        // VR-41: depreciation and amortization run once per period per asset class.
        Schema::create('system_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_type', 30);
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->string('scope', 60)->default('');
            $table->foreignId('journal_header_id')->nullable()->constrained();
            $table->foreignId('run_by')->constrained('users');
            $table->timestampTz('run_at')->useCurrent();

            $table->unique(['run_type', 'period_id', 'scope']);
        });

        foreach (['advances', 'advance_settlements', 'reconciliations', 'system_runs'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_runs');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('advance_settlements');

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['advance_id']);
        });

        Schema::dropIfExists('advances');
    }
};
