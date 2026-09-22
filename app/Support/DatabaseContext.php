<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Sets actor context for execution paths that have no HTTP request: queued jobs,
 * console commands, seeders and scheduled work. Without this, anything the scheduler
 * posts is audited with a NULL actor.
 */
final class DatabaseContext
{
    public static function actingAs(?int $userId, ?string $reason = null): void
    {
        self::set('app.user_id', $userId === null ? '' : (string) $userId);

        if ($reason !== null) {
            self::set('app.audit_reason', $reason);
        }
    }

    /** Inside a transaction, SET LOCAL is correct and unwinds automatically. */
    public static function setLocal(string $name, string $value): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, true)', [$name, $value]);
    }

    public static function set(string $name, string $value): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, false)', [$name, $value]);
    }

    public static function clear(): void
    {
        foreach (['app.user_id', 'app.request_id', 'app.actor_ip', 'app.audit_reason'] as $name) {
            self::set($name, '');
        }
    }
}
