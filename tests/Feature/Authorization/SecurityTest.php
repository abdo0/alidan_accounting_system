<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnforceAbsoluteSessionTimeout;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/** Document B §8: authentication, sessions and their logging. */
class SecurityTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    public function an_account_locks_after_five_failed_attempts_and_stays_locked(): void
    {
        $user = $this->userWithRole('accountant');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'wrong'])->call('authenticate');
            RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));
        }

        $user->refresh();
        $this->assertSame(5, $user->failed_attempts);
        $this->assertTrue($user->isLocked());
        $this->assertTrue(DB::table('audit_log')->where('action', 'account_locked')->where('record_id', $user->id)->exists());

        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate')->assertHasErrors();
        $this->assertGuest();
    }

    #[Test]
    public function a_successful_sign_in_is_logged_and_clears_the_failure_count(): void
    {
        $user = $this->userWithRole('accountant');
        $user->forceFill(['failed_attempts' => 3])->save();

        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->failed_attempts);
        $this->assertTrue(DB::table('audit_log')->where('action', 'login')->where('record_id', $user->id)->exists());
    }

    #[Test]
    public function a_session_ends_twelve_hours_after_sign_in_whatever_the_activity(): void
    {
        $this->actingAs($this->userWithRole('accountant'));

        $this->withSession(['authenticated_at' => now()->getTimestamp() - EnforceAbsoluteSessionTimeout::ABSOLUTE_SECONDS - 1])
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest();
    }

    #[Test]
    public function sessions_idle_out_after_twenty_minutes_and_passwords_use_argon2id(): void
    {
        $session = (string) file_get_contents(config_path('session.php'));
        $this->assertStringContainsString("env('SESSION_LIFETIME', 20)", $session);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'strict')", $session);
        $this->assertTrue(config('session.http_only'));
        $this->assertStringContainsString("env('HASH_DRIVER', 'argon2id')", (string) file_get_contents(config_path('hashing.php')), 'Production hashes with Argon2id unless told otherwise.');
    }
}
