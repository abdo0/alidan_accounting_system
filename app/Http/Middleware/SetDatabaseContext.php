<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishes actor context to PostgreSQL so the audit triggers can attribute changes.
 *
 * Deliberately set_config(..., is_local => false) and NOT `SET LOCAL`: middleware runs
 * outside any transaction, where `SET LOCAL` emits
 *   WARNING: SET LOCAL can only be used in transaction blocks
 * and silently does nothing -- which would leave every audit row with a NULL actor.
 * Session scope is safe here because PDO connections are not persistent, so the
 * setting lives for this request's connection only. It is cleared on terminate for
 * the case where a connection is reused by a long-lived worker (Octane, queue).
 */
class SetDatabaseContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->headers->get('X-Request-Id') ?: Str::uuid());
        $request->attributes->set('request_id', $requestId);

        $this->apply([
            'app.user_id' => (string) ($request->user()?->getAuthIdentifier() ?? ''),
            'app.request_id' => $requestId,
            'app.actor_ip' => (string) ($request->ip() ?? ''),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->apply([
            'app.user_id' => '',
            'app.request_id' => '',
            'app.actor_ip' => '',
            'app.audit_reason' => '',
        ]);
    }

    /** @param  array<string, string>  $settings */
    private function apply(array $settings): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($settings as $name => $value) {
            DB::statement('SELECT set_config(?, ?, false)', [$name, $value]);
        }
    }
}
