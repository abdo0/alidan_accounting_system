<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-factor authentication is required for any role that can post or approve
     * (docs/02 §2.6). Both columns are encrypted at the model layer.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->timestampTz('mfa_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'app_authentication_secret',
                'app_authentication_recovery_codes',
                'mfa_confirmed_at',
            ]);
        });
    }
};
