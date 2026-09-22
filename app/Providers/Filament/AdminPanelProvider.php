<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Enums\NavigationGroup;
use App\Http\Middleware\SetDatabaseContext;
use App\Http\Middleware\SetLocale;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // Required for any role that can post or approve (docs/02 §2.6). Enforced
            // per-role rather than globally so a read-only viewer is not burdened.
            ->multiFactorAuthentication(AppAuthentication::make()->recoverable())
            ->colors([
                'primary' => Color::Amber,
            ])
            // Inter has weak Arabic coverage; this family carries both scripts so the
            // panel stays legible when the locale flips to ar.
            ->font('IBM Plex Sans Arabic')
            ->navigationGroups(NavigationGroup::class)
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn (): string => __('navigation.locale_switch'))
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('locale.switch', [
                        'locale' => app()->getLocale() === 'ar' ? 'en' : 'ar',
                    ])),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetDatabaseContext::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
