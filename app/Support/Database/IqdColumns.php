<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Money columns are created as numeric by the schema builder and then moved onto
 * the iqd_amount / iqd_signed domains, which the builder has no type for. The
 * domain is what rejects a fractional dinar at the database (VR-19).
 */
final class IqdColumns
{
    /** @param  array<string, 'amount'|'signed'>  $columns */
    public static function apply(string $table, array $columns): void
    {
        foreach ($columns as $column => $kind) {
            $domain = $kind === 'signed' ? 'iqd_signed' : 'iqd_amount';

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s USING %s::%s',
                $table, $column, $domain, $column, $domain,
            ));
        }
    }
}
