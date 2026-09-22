<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Document B §8: sessions end after 20 minutes idle (the session lifetime) and,
 * regardless of activity, 12 hours after sign-in.
 */
class EnforceAbsoluteSessionTimeout
{
    public const ABSOLUTE_SECONDS = 12 * 60 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        $started = $request->session()->get('authenticated_at');

        if ($request->user() !== null && $started === null) {
            $request->session()->put('authenticated_at', now()->getTimestamp());
        } elseif ($request->user() !== null && now()->getTimestamp() - (int) $started > self::ABSOLUTE_SECONDS) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        return $next($request);
    }
}
