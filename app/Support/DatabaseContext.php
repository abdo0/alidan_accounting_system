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

    /**
     * Runs $callback in a transaction whose audit rows carry a business action and,
     * where given, a reason. Document B §7 makes the reason mandatory for reversal,
     * reclassification, period reopen, parameter change and mapping change; the
     * services that do those things pass it here.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withAudit(string $action, ?string $reason, callable $callback): mixed
    {
        return DB::transaction(function () use ($action, $reason, $callback): mixed {
            $previousAction = self::current('app.audit_action');
            $previousReason = self::current('app.audit_reason');

            self::setLocal('app.audit_action', $action);
            self::setLocal('app.audit_reason', $reason ?? '');

            try {
                return $callback();
            } finally {
                self::setLocal('app.audit_action', $previousAction);
                self::setLocal('app.audit_reason', $previousReason);
            }
        });
    }

    public static function current(string $name): string
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return '';
        }

        return (string) (DB::selectOne('SELECT current_setting(?, true) AS v', [$name])->v ?? '');
    }

    public static function clear(): void
    {
        foreach (['app.user_id', 'app.request_id', 'app.actor_ip', 'app.audit_reason', 'app.audit_action'] as $name) {
            self::set($name, '');
        }
    }
}
