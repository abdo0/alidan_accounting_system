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
            $table->string('username', 60)->nullable()->unique()->after('name');
            $table->string('name_ar', 150)->nullable()->after('username');
            $table->string('locale', 5)->default('ar')->after('name_ar');
            // Arabic-Indic or Latin digits in figures, per user (Document B §10).
            $table->string('numeral_system', 4)->default('latn')->after('locale');
            $table->boolean('is_active')->default(true)->after('numeral_system');
            $table->boolean('is_service_account')->default(false)->after('is_active');
            $table->string('job_title', 120)->nullable();
            // Account lock after five failed attempts (Document B §8).
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_supported CHECK (locale IN ('en','ar'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_numeral_system_valid CHECK (numeral_system IN ('latn','arab'))");

        DB::statement('SELECT attach_audit(?)', ['users']);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_numeral_system_valid');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_locale_supported');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'username', 'name_ar', 'locale', 'numeral_system', 'is_active', 'is_service_account',
                'job_title', 'failed_attempts', 'locked_until', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
