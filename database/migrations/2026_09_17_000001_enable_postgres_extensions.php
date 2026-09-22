<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extensions the accounting schema depends on.
     *
     * ltree    - cost centre hierarchy paths (subtree rollup is the most frequent
     *            query in management reporting, so it gets a native index type)
     * pgcrypto - digest() for the journal entry tamper-evidence hash chain
     * pg_trgm  - ILIKE '%x%' search on master data; no b-tree can serve it
     */
    public function up(): void
    {
        foreach (['ltree', 'pgcrypto', 'pg_trgm'] as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }
    }

    public function down(): void
    {
        foreach (['pg_trgm', 'pgcrypto', 'ltree'] as $extension) {
            DB::statement("DROP EXTENSION IF EXISTS {$extension}");
        }
    }
};
