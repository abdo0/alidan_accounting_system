<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Two-factor enrolment is optional, including for the system administrator.
 * A role can still recommend it; this only shows the reminder, it never blocks.
 */
class RequireMfaForSensitiveRoles
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->requiresMfa()
            && ! $user->hasEnrolledMfa()
            && ! $request->session()->get('mfa_optional_notice_shown')
        ) {
            Notification::make()
                ->title(__('auth.mfa_required'))
                ->warning()
                ->persistent()
                ->send();

            $request->session()->put('mfa_optional_notice_shown', true);
        }

        return $next($request);
    }
}
