<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the back-references between modules that could not be declared when
     * their tables were created. Each module creates its own table; this closes the
     * cycles once both ends exist.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->foreign('retained_earnings_account_id')->references('id')->on('accounts');
            $table->foreign('current_earnings_account_id')->references('id')->on('accounts');
            $table->foreign('suspense_account_id')->references('id')->on('accounts');
            $table->foreign('rounding_account_id')->references('id')->on('accounts');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreign('default_cost_centre_id')->references('id')->on('cost_centres');
        });

        Schema::table('approval_rules', function (Blueprint $table): void {
            $table->foreign('entity_id')->references('id')->on('entities');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('default_entity_id')->references('id')->on('entities');
            $table->foreign('default_cost_centre_id')->references('id')->on('cost_centres');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['default_entity_id']);
            $table->dropForeign(['default_cost_centre_id']);
        });

        Schema::table('approval_rules', function (Blueprint $table): void {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropForeign(['default_cost_centre_id']);
        });

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropForeign(['retained_earnings_account_id']);
            $table->dropForeign(['current_earnings_account_id']);
            $table->dropForeign(['suspense_account_id']);
            $table->dropForeign(['rounding_account_id']);
        });
    }
};
