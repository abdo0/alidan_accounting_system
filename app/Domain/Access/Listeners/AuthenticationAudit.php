<?php

declare(strict_types=1);

namespace App\Domain\Access\Listeners;

use App\Domain\Shared\Audit\AuditRecorder;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Document B §8: every authentication event is logged, and an account locks after
 * five failed attempts. A locked account stays locked until a System Administrator
 * unlocks it, unless config('shh.security.lockout_minutes') sets a timed lock.
 */
final class AuthenticationAudit
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly AuditRecorder $audit) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, $this->login(...));
        $events->listen(Failed::class, $this->failed(...));
        $events->listen(Logout::class, $this->logout(...));
    }

    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'failed_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        session()->put('authenticated_at', now()->getTimestamp());

        $this->audit->event('login', 'users', $event->user->id, null, null, $event->user->id);
    }

    public function failed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->event('login_failed', 'users', $user?->id, null, ['identifier' => $event->credentials['email'] ?? null], $user?->id);

        if ($user === null) {
            return;
        }

        $attempts = $user->failed_attempts + 1;
        $user->forceFill(['failed_attempts' => $attempts]);

        if ($attempts >= self::MAX_ATTEMPTS && $user->locked_until === null) {
            $minutes = config('shh.security.lockout_minutes');
            $user->forceFill(['locked_until' => $minutes === null ? now()->addYears(100) : now()->addMinutes((int) $minutes)]);
            $this->audit->event('account_locked', 'users', $user->id, __('auth.locked'), ['attempts' => $attempts], $user->id);
        }

        $user->save();
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->event('logout', 'users', $event->user->id, null, null, $event->user->id);
        }
    }
}
