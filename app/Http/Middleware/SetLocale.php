<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the interface language, in order: an explicit session choice, then the
 * signed-in user's stored preference, then the application default.
 *
 * Filament needs nothing further for RTL: its layout renders
 * dir="{{ __('filament-panels::layout.direction') }}" and the shipped ar/layout.php
 * sets direction => rtl, so setting the locale flips the whole panel.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.available_locales', ['en' => 'English']));

        $user = $request->user();

        // Defensive: this middleware belongs after StartSession, but a console-driven
        // or stateless request must not fatal here.
        $locale = ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? ($user instanceof User ? $user->locale : null)
            ?? config('app.locale');

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
