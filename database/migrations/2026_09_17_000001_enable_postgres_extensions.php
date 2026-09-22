<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extensions and value domains the accounting schema depends on.
     *
     * pgcrypto   - digest() for the journal tamper-evidence hash chain
     * pg_trgm    - ILIKE '%x%' search on Arabic and English master data
     * btree_gist - EXCLUDE constraints that combine equality with a date range, so
     *              two values of one parameter can never be effective on the same day
     *
     * iqd_amount is the only type a money column may have. numeric(19,0) is not
     * enough: PostgreSQL rounds 1.5 to 2 on insert, which would turn a fractional
     * dinar into a silently different figure instead of rejecting it (VR-19).
     */
    public function up(): void
    {
        foreach (['pgcrypto', 'pg_trgm', 'btree_gist'] as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }

        // migrate:fresh drops tables but not types, so a rebuild starts clean here.
        DB::statement('DROP DOMAIN IF EXISTS iqd_amount CASCADE');
        DB::statement('DROP DOMAIN IF EXISTS iqd_signed CASCADE');

        DB::statement(<<<'SQL'
            CREATE DOMAIN iqd_amount AS numeric
                CHECK (VALUE = trunc(VALUE) AND VALUE >= 0 AND VALUE < 1e19)
        SQL);

        // For figures that may legitimately be negative: a contract amendment that
        // reduces the value, an amount difference carried from the source.
        DB::statement(<<<'SQL'
            CREATE DOMAIN iqd_signed AS numeric
                CHECK (VALUE = trunc(VALUE) AND abs(VALUE) < 1e19)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP DOMAIN IF EXISTS iqd_signed');
        DB::statement('DROP DOMAIN IF EXISTS iqd_amount');

        foreach (['btree_gist', 'pg_trgm', 'pgcrypto'] as $extension) {
            DB::statement("DROP EXTENSION IF EXISTS {$extension}");
        }
    }
};
