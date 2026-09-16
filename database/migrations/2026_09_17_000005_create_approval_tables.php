<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maker-checker. Cross-cutting rather than per-module: V-14 in the posting
     * validator depends on it, and every subledger registers its own rules against
     * the same engine.
     */
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();   // FK added with entities
            $table->string('document_type', 40);
            $table->string('name', 150);
            $table->jsonb('condition')->default(DB::raw("'{}'::jsonb"));
            $table->unsignedSmallInteger('step_no')->default(1);
            $table->string('approver_role', 60)->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['document_type', 'step_no']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->string('status', 20)->default('pending');   // pending|approved|rejected|recalled
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->unsignedSmallInteger('total_steps')->default(1);
            $table->decimal('amount', 20, 4)->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();

            $table->unique(['document_type', 'document_id']);
            $table->index(['status', 'current_step']);
        });

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_no');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action', 20);            // approve|reject|delegate|recall
            $table->foreignId('delegated_to')->nullable()->constrained('users');
            $table->text('comment')->nullable();
            $table->timestampTz('acted_at')->useCurrent();
        });

        // Delegation is explicit and time-boxed, never a shared login.
        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->string('document_type', 40)->nullable();   // null = all
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['from_user_id', 'starts_on', 'ends_on']);
        });

        DB::statement('ALTER TABLE approval_delegations ADD CONSTRAINT delegation_dates_valid CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE approval_delegations ADD CONSTRAINT delegation_not_self CHECK (from_user_id <> to_user_id)');

        foreach (['approval_rules', 'approval_requests', 'approval_actions', 'approval_delegations'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_rules');
    }
};
