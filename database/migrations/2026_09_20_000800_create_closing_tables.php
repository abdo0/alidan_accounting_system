<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly closing (M15): the 25-task template of Document A §13 and its instance
     * for every period. Final close waits for every task (Document B §4.11).
     */
    public function up(): void
    {
        Schema::create('closing_task_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('task_no')->unique();
            $table->string('name', 200);
            $table->string('name_ar', 200);
            $table->string('source', 60)->nullable();
            $table->timestampsTz();
        });

        Schema::create('closing_checklist', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->unsignedSmallInteger('task_no');
            $table->string('name', 200);
            $table->string('name_ar', 200);
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestampTz('completed_at')->nullable();
            $table->date('review_date')->nullable();
            $table->text('comments')->nullable();
            $table->timestampsTz();

            $table->unique(['period_id', 'task_no']);
        });

        DB::statement("ALTER TABLE closing_checklist ADD CONSTRAINT closing_checklist_status_valid CHECK (status IN ('pending','completed','exception'))");
        DB::statement("ALTER TABLE closing_checklist ADD CONSTRAINT closing_checklist_completed_by CHECK (status <> 'completed' OR completed_by IS NOT NULL)");

        foreach (['closing_task_templates', 'closing_checklist'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_checklist');
        Schema::dropIfExists('closing_task_templates');
    }
};
