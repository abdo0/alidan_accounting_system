<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role-based access (M17, Document C tab 19). A grant carries a scope: All, or
     * Own -- the Accounting Data Entry role sees and edits only what it created.
     * Scope is evaluated server-side in the services, never only in the UI.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('group', 50);
            $table->string('label_en', 150);
            $table->string('label_ar', 150)->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestampsTz();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->nullable()->unique();   // ROLE-01 ... ROLE-08
            $table->string('name', 60)->unique();
            $table->string('label_en', 120);
            $table->string('label_ar', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_read_only')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('requires_mfa')->default(false);
            $table->timestampsTz();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 12)->default('all');
            $table->primary(['role_id', 'permission_id']);
        });

        DB::statement("ALTER TABLE role_permissions ADD CONSTRAINT role_permissions_scope_valid CHECK (scope IN ('all','own','conditional'))");

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->timestampTz('granted_at')->useCurrent();
            $table->primary(['role_id', 'user_id']);
        });

        foreach (['permissions', 'roles', 'role_permissions', 'user_roles'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
