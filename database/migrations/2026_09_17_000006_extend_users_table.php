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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name_ar', 150)->nullable()->after('name');
            $table->string('locale', 5)->default('en')->after('name_ar');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->boolean('is_service_account')->default(false)->after('is_active');
            $table->unsignedBigInteger('default_entity_id')->nullable()->after('is_service_account');
            $table->unsignedBigInteger('default_cost_centre_id')->nullable()->after('default_entity_id');
            $table->string('job_title', 120)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_supported CHECK (locale IN ('en','ar'))");

        // A service account drives integrations and scheduled jobs. It must never be
        // able to approve -- that is the whole point of maker-checker.
        DB::statement('SELECT attach_audit(?)', ['users']);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_locale_supported');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'name_ar', 'locale', 'is_active', 'is_service_account',
                'default_entity_id', 'default_cost_centre_id',
                'job_title', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
