<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Two-factor authentication is required for any role that can post or approve
 * (docs/02 §2.6). The panel offers enrolment; this makes it unavoidable.
 *
 * Holding such a role without having enrolled leaves a user able to reach the panel
 * but not to do anything sensitive -- the policies already refuse on
 * hasSatisfiedMfaRequirement(). Left there, they would meet a series of missing
 * buttons and no explanation, so they are sent to the profile page instead.
 */
class RequireMfaForSensitiveRoles
{
    /**
     * Routes that must stay reachable, or the redirect has nowhere to land: the
     * profile page where enrolment happens, the Livewire endpoint that page posts
     * to, and the way out.
     */
    private const ALLOWED = [
        'filament.admin.auth.profile',
        'filament.admin.auth.logout',
        'filament.admin.auth.login',
        'locale.switch',
        'livewire.update',
        'livewire.upload-file',
        'livewire.preview-file',
    ];

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasSatisfiedMfaRequirement()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        // A background or API caller gets a refusal, not a redirect to a form.
        if ($request->expectsJson()) {
            return response()->json(
                ['message' => __('auth.mfa_required')],
                Response::HTTP_FORBIDDEN,
            );
        }

        Notification::make()
            ->title(__('auth.mfa_required'))
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to(Filament::getPanel('admin')->getProfileUrl());
    }
}
