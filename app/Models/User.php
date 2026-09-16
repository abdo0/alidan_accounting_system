<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'name_ar', 'email', 'password', 'locale', 'is_active',
    'is_service_account', 'default_entity_id', 'default_cost_centre_id', 'job_title',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
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
}
