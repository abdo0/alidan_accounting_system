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
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();       // ledger.post, ap.approve, ...
            $table->string('group', 50);
            $table->string('label_en', 150);
            $table->string('label_ar', 150)->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestampsTz();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('label_en', 120);
            $table->string('label_ar', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_read_only')->default(false);   // the auditor role
            $table->boolean('is_system')->default(false);      // cannot be deleted
            $table->boolean('requires_mfa')->default(false);   // posting/approval roles
            $table->timestampsTz();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->timestampTz('granted_at')->useCurrent();
            $table->primary(['role_id', 'user_id']);
        });

        // Segregation of duties will be overridden in a 3-10 person finance team
        // (assumption A-06). The design's answer is that overrides are logged and
        // surfaced, not that they are prevented.
        Schema::create('sod_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->string('rule', 60);                  // creator_is_approver, ...
            $table->foreignId('user_id')->constrained();
            $table->foreignId('authorised_by')->constrained('users');
            $table->text('justification');
            $table->text('compensating_control')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['document_type', 'document_id']);
            $table->index('occurred_at');
        });

        foreach (['permissions', 'roles', 'permission_role', 'role_user', 'sod_overrides'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sod_overrides');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
