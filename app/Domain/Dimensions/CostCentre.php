<?php

declare(strict_types=1);

namespace App\Domain\Dimensions;

use App\Domain\Organisation\Entity;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $entity_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property string $cost_centre_type
 * @property string|null $department
 * @property string|null $branch
 * @property string|null $reporting_group
 * @property string|null $path
 * @property int $depth
 * @property bool $is_postable
 * @property bool $allows_revenue
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property bool $is_active
 */
class CostCentre extends Model
{
    use HasTranslatableName;

    public const OPERATING = 'operating';

    public const SUPPORT = 'support';

    public const PROJECT = 'project';

    public const ADMIN = 'admin';

    public const STATISTICAL = 'statistical';

    protected $fillable = [
        'entity_id', 'parent_id', 'code', 'name', 'name_ar', 'cost_centre_type',
        'department', 'branch', 'reporting_group', 'manager_user_id', 'path', 'depth',
        'is_postable', 'allows_revenue', 'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'allows_revenue' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'depth' => 'integer',
        ];
    }

    /** @return BelongsTo<CostCentre, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<CostCentre, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('access_level');
    }

    /**
     * Every centre at or below this one. Uses the ltree operator rather than a
     * recursive CTE, because rollup is the most frequent query in management
     * reporting and this is served by a GiST index.
     *
     * @param  Builder<CostCentre>  $query
     * @return Builder<CostCentre>
     */
    public function scopeUnder(Builder $query, string $path): Builder
    {
        return $query->whereRaw('path <@ ?::ltree', [$path]);
    }

    /** A closed centre keeps reporting but stops being selectable. */
    public function isActiveOn(CarbonImmutable $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->effective_from !== null && $date->lessThan($this->effective_from)) {
            return false;
        }

        return $this->effective_to === null || $date->lessThanOrEqualTo($this->effective_to);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->displayName();
    }
}
