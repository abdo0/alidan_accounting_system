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
     * Dimensions and parties (M01, M02; Document C tabs 05-09).
     *
     * Counterparties are not cost centres (Document B §4.1). A contractor, supplier,
     * custodian, shareholder or government body is one row here, typed by flags; a
     * cost centre is purely functional.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('project_type', 12);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_type_valid CHECK (project_type IN ('project','corporate'))");

        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('cc_type', 12);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE cost_centers ADD CONSTRAINT cost_centers_type_valid CHECK (cc_type IN ('project','sga','operations'))");

        Schema::create('counterparties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 300);
            $table->string('name_ar', 300)->nullable();
            $table->string('cp_type', 20);
            $table->string('role_description', 200)->nullable();
            $table->boolean('is_contractor')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_advance_holder')->default(false);
            $table->boolean('is_shareholder')->default(false);
            $table->boolean('is_employee')->default(false);
            $table->boolean('is_government')->default(false);
            // The free-text name the authoritative ledger carried, so migration stays
            // traceable (Document B §4.1).
            $table->text('source_alias')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE counterparties ADD CONSTRAINT counterparties_type_valid CHECK (cp_type IN ('contractor','supplier','employee','advance_holder','shareholder','government','other'))");
        DB::statement('CREATE INDEX counterparties_name_trgm ON counterparties USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX counterparties_name_ar_trgm ON counterparties USING gin (name_ar gin_trgm_ops)');

        // A near-duplicate (CN-11 Gulf Shield / CN-04 Deraa Al-Khaleej) is merged
        // under one code and the other name kept here.
        Schema::create('counterparty_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('counterparty_id')->constrained();
            $table->string('alias', 300);
            $table->string('legacy_code', 20)->nullable();
            $table->string('source', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['counterparty_id', 'alias']);
        });

        Schema::create('shareholders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('counterparty_id')->unique()->constrained();
            $table->string('code', 20)->unique();
            $table->foreignId('loan_account_id')->nullable()->unique()->constrained('accounts');
            $table->foreignId('current_account_id')->nullable()->unique()->constrained('accounts');
            $table->foreignId('capital_account_id')->nullable()->unique()->constrained('accounts');
            $table->decimal('ownership_pct', 7, 4)->nullable();
            $table->boolean('is_approved_financier')->default(false);
            $table->timestampsTz();
        });

        Schema::create('responsibility_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->foreignId('linked_counterparty_id')->nullable()->constrained('counterparties');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        // Which custodian and responsibility centre an advance or personal-receivable
        // account belongs to. This is what lets RC be derived (CONF-06) and VR-28
        // name the individual, without a code list in the application.
        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreignId('holder_counterparty_id')->nullable()->after('purpose')->constrained('counterparties');
            $table->foreignId('responsibility_center_id')->nullable()->after('holder_counterparty_id')->constrained('responsibility_centers');
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('contract_no', 40);
            $table->string('title', 300);
            $table->string('title_ar', 300)->nullable();
            $table->string('contract_type', 20);
            $table->foreignId('counterparty_id')->nullable()->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->decimal('contract_value', 21, 0)->nullable();
            $table->date('signed_on')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('term_years')->nullable();
            $table->decimal('retention_pct', 5, 2)->nullable();
            $table->decimal('advance_recovery_pct', 5, 2)->nullable();
            $table->string('status', 12)->default('pending');
            // Fields the source marks "To be defined" load as NULL and are named
            // here, never filled with an assumed value (MIG-09).
            $table->jsonb('pending_fields')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'contract_no']);
        });

        IqdColumns::apply('contracts', ['contract_value' => 'amount']);
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_type_valid CHECK (contract_type IN ('concession','construction','supply','services','consulting','other'))");
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_valid CHECK (status IN ('pending','active','completed','terminated'))");

        Schema::create('contract_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained();
            $table->string('amendment_no', 40);
            $table->string('amendment_type', 20);
            $table->decimal('value_change', 21, 0)->default(0);
            $table->date('approved_on')->nullable();
            $table->string('approval_ref', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->unique(['contract_id', 'amendment_no']);
        });

        IqdColumns::apply('contract_amendments', ['value_change' => 'signed']);
        DB::statement("ALTER TABLE contract_amendments ADD CONSTRAINT contract_amendments_type_valid CHECK (amendment_type IN ('amendment','change_order'))");

        Schema::create('work_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained();
            $table->string('code', 40);
            $table->string('name', 300);
            $table->string('name_ar', 300)->nullable();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('cost_center_id')->nullable()->constrained();
            $table->decimal('budget_value', 21, 0)->nullable();
            $table->timestampsTz();

            $table->unique(['contract_id', 'code']);
        });

        IqdColumns::apply('work_packages', ['budget_value' => 'amount']);

        // Each cash, safe or bank account is bound to exactly one GL account
        // (Document C tab 09). Its book balance is never stored here (VR-57).
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('code', 20);
            $table->foreignId('account_id')->unique()->constrained();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('ba_type', 10);
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('custodian_id')->nullable()->constrained('counterparties');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users');
            $table->string('bank_name', 200)->nullable();
            $table->string('account_number', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_type_valid CHECK (ba_type IN ('cash','safe','bank','transit'))");

        foreach ([
            'projects', 'cost_centers', 'counterparties', 'counterparty_aliases', 'shareholders',
            'responsibility_centers', 'contracts', 'contract_amendments', 'work_packages', 'bank_accounts',
        ] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('work_packages');
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contracts');

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsibility_center_id');
            $table->dropConstrainedForeignId('holder_counterparty_id');
        });

        Schema::dropIfExists('responsibility_centers');
        Schema::dropIfExists('shareholders');
        Schema::dropIfExists('counterparty_aliases');
        Schema::dropIfExists('counterparties');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('projects');
    }
};
