<?php

declare(strict_types=1);

namespace App\Domain\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name', 'group', 'label_en', 'label_ar', 'is_sensitive',
    ];

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean'];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withPivot('scope');
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' && $this->label_ar
            ? $this->label_ar
            : $this->label_en;
    }
}
