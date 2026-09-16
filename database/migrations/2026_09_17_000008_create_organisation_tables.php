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
        // This installation posts in IQD only. The table and the currency_code
        // columns remain because retrofitting a currency through every financial
        // table and the balance store later is far more expensive than carrying a
        // 3-character column now.
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name', 60);
            $table->string('name_ar', 60)->nullable();
            $table->string('symbol', 10)->nullable();
            // IQD has no minor unit in practice. Storing it with 2 decimals produces
            // amounts no bank statement will ever match.
            $table->unsignedSmallInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('entities');
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('legal_name', 200)->nullable();
            $table->string('legal_name_ar', 200)->nullable();
            $table->string('tax_registration_no', 50)->nullable();
            $table->string('commercial_register_no', 50)->nullable();
            $table->char('functional_currency', 3);
            $table->boolean('is_consolidation_node')->default(false);
            // Resolved by code through config/accounting.php, so the official chart's
            // numbering can differ from the placeholder without breaking the engine.
            $table->unsignedBigInteger('retained_earnings_account_id')->nullable();
            $table->unsignedBigInteger('current_earnings_account_id')->nullable();
            $table->unsignedBigInteger('suspense_account_id')->nullable();
            $table->unsignedBigInteger('rounding_account_id')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('functional_currency')->references('code')->on('currencies');
        });

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained();
            $table->string('code', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');   // open | closed
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['entity_id', 'code']);
        });

        DB::statement('ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_year_dates_valid CHECK (ends_on > starts_on)');
        DB::statement("ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_year_status_valid CHECK (status IN ('open','closed'))");

        Schema::create('fiscal_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained();
            $table->foreignId('entity_id')->constrained();
            // Period 13 is the adjustment period. Its dates must be set deliberately
            // (both = year end) or V-04 rejects every audit adjustment.
            $table->unsignedSmallInteger('period_no');
            $table->string('name', 40);
            $table->string('name_ar', 40)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_adjustment_period')->default(false);
            $table->string('status', 20)->default('open');
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestampTz('opened_at')->nullable();
            $table->timestampsTz();

            $table->unique(['fiscal_year_id', 'period_no']);
            $table->index(['entity_id', 'starts_on', 'ends_on']);
        });

        DB::statement('ALTER TABLE fiscal_periods ADD CONSTRAINT fiscal_period_dates_valid CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE fiscal_periods ADD CONSTRAINT fiscal_period_status_valid CHECK (status IN ('open','soft_closed','closed','permanently_closed'))");

        foreach (['currencies', 'entities', 'fiscal_years', 'fiscal_periods'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('currencies');
    }
};
