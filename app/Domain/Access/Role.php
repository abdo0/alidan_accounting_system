<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'code', 'name', 'label_en', 'label_ar', 'description',
        'is_read_only', 'is_system', 'requires_mfa',
    ];

    protected function casts(): array
    {
        return [
            'is_read_only' => 'boolean',
            'is_system' => 'boolean',
            'requires_mfa' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot('scope');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['granted_by', 'granted_at']);
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' && $this->label_ar
            ? $this->label_ar
            : $this->label_en;
    }
}
