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
        // Tax CODES are dependency-free master data and journal_lines.tax_code_id
        // needs them, so they belong here rather than with the tax subledger.
        Schema::create('tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('tax_type', 30);
            $table->string('calculation', 20)->default('percentage');
            $table->boolean('is_recoverable')->default(false);
            $table->foreignId('payable_account_id')->nullable()->constrained('accounts');
            $table->foreignId('receivable_account_id')->nullable()->constrained('accounts');
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts');
            $table->string('statutory_box', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT tax_type_valid CHECK (tax_type IN ('sales_tax','withholding','income_tax','stamp_duty','social_security','customs'))");

        // Effective-dated and never edited in place: a rate change must not
        // retrospectively alter a return that has already been filed.
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_code_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 9, 6);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->unique(['tax_code_id', 'effective_from']);
        });

        // The six books of prime entry, plus the journals every later module needs.
        // Seeding only the six means a later module either abuses the general journal
        // or gets a gapless sequence that starts at 1 in month seven.
        Schema::create('journals', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('journal_type', 30);
            $table->foreignId('default_debit_account_id')->nullable()->constrained('accounts');
            $table->foreignId('default_credit_account_id')->nullable()->constrained('accounts');
            // Only the general journal accepts hand-written entries by default.
            $table->boolean('allows_manual_entry')->default(false);
            // System journals are written by depreciation, allocation, closing and
            // reversal runs, whose entries are approval-exempt (they have no maker).
            $table->boolean('is_system')->default(false);
            $table->string('sequence_prefix', 10);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE journals ADD CONSTRAINT journal_type_valid CHECK (journal_type IN ('sales','purchases','returns_in','returns_out','cash','petty_cash','general','payroll','fixed_assets','inventory','closing','allocation','opening'))");

        foreach (['tax_codes', 'tax_rates', 'journals'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_codes');
    }
};
