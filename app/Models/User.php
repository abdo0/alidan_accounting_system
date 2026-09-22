<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Concerns\HasRoles;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string|null $name_ar
 * @property string $locale
 * @property bool $is_active
 * @property bool $is_service_account
 * @property int|null $default_entity_id
 * @property int|null $default_cost_centre_id
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $mfa_confirmed_at
 * @property string|null $app_authentication_secret
 * @property array<int, string>|null $app_authentication_recovery_codes
 */
#[Fillable([
    'name', 'name_ar', 'email', 'password', 'locale', 'is_active',
    'is_service_account', 'default_entity_id', 'default_cost_centre_id', 'job_title',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_service_account' => 'boolean',
            'last_login_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Without this contract every authenticated user reaches the panel once APP_ENV
     * is not local. A deactivated account and a service account must never get in --
     * a service account exists to drive integrations, and integrations never approve.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && ! $this->is_service_account
            && $this->roles()->exists();
    }

    public function getFilamentName(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->mfa_confirmed_at = $secret === null ? null : now();
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<int, string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  array<int, string>|null  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
