<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data migration evidence (M18): each run of the importer, and each of the thirty
     * reconciliation controls it was measured against (Document C tab 23).
     */
    public function up(): void
    {
        Schema::create('migration_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_ref', 30)->unique();
            $table->string('source_file', 255);
            $table->char('source_sha256', 64);
            $table->string('mode', 10);
            $table->string('status', 12);
            $table->string('signoff_ref', 120)->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users');
            $table->jsonb('summary')->nullable();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('finished_at')->nullable();
        });

        DB::statement("ALTER TABLE migration_runs ADD CONSTRAINT migration_runs_mode_valid CHECK (mode IN ('dry_run','commit'))");
        DB::statement("ALTER TABLE migration_runs ADD CONSTRAINT migration_runs_status_valid CHECK (status IN ('running','completed','failed'))");

        Schema::create('migration_control', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_run_id')->constrained()->restrictOnDelete();
            $table->string('control_code', 10);
            $table->string('control_name', 200);
            $table->text('source_value');
            $table->text('system_value');
            $table->string('status', 6);
            $table->text('notes')->nullable();
            $table->timestampTz('run_at')->useCurrent();

            $table->unique(['migration_run_id', 'control_code']);
        });

        DB::statement("ALTER TABLE migration_control ADD CONSTRAINT migration_control_status_valid CHECK (status IN ('pass','fail'))");

        Schema::table('journal_headers', function (Blueprint $table): void {
            $table->foreign('migration_run_id')->references('id')->on('migration_runs');
        });

        foreach (['migration_runs', 'migration_control'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_headers', function (Blueprint $table): void {
            $table->dropForeign(['migration_run_id']);
        });

        Schema::dropIfExists('migration_control');
        Schema::dropIfExists('migration_runs');
    }
};
