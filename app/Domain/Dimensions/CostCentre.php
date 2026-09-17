<?php

declare(strict_types=1);

namespace App\Domain\Dimensions;

use App\Domain\Ledger\Account;
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
 * @property int|null $control_class
 * @property string|null $responsibility_unit
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

    /**
     * The UAS fixes the taxonomy and reserves chart classes 5-9 for it. Each type maps
     * to exactly one control class, which is what makes the composite statutory code
     * (<control class><use element>, e.g. ٥٣١) derivable.
     */
    public const PRODUCTION = 'production';         // مراكز الإنتاج -> 5

    public const PROD_SERVICE = 'prod_service';     // مراكز الخدمات الإنتاجية -> 6

    public const MARKETING = 'marketing';           // مراكز الخدمات التسويقية -> 7

    public const ADMIN = 'admin';                   // مراكز الخدمات الإدارية -> 8

    public const CAPITAL = 'capital';               // مراكز العمليات الرأسمالية -> 9

    /** @var array<string, int> */
    public const CONTROL_CLASSES = [
        self::PRODUCTION => 5,
        self::PROD_SERVICE => 6,
        self::MARKETING => 7,
        self::ADMIN => 8,
        self::CAPITAL => 9,
    ];

    protected $fillable = [
        'entity_id', 'parent_id', 'code', 'name', 'name_ar', 'cost_centre_type',
        'control_class', 'department', 'branch', 'responsibility_unit',
        'reporting_group', 'manager_user_id', 'path', 'depth',
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
            'control_class' => 'integer',
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

    /**
     * The control class this centre's costs are gathered under, 5-9. Falls back to the
     * taxonomy when the column has not been set, so the mapping lives in one place.
     */
    public function controlClass(): ?int
    {
        return $this->control_class ?? self::CONTROL_CLASSES[$this->cost_centre_type] ?? null;
    }

    /**
     * The composite statutory code the distribution grid keys on:
     * <control class><use element, 2 digits>. ٥٣١ is "salaries and wages, production
     * centres" -- account 3115 in a production centre gives element 31 and class 5.
     *
     * Null where the account is not a use: the grid covers elements 31-39 only.
     */
    public function compositeCodeFor(Account $account): ?string
    {
        $element = $account->elementCode();
        $class = $this->controlClass();

        if ($element === null || $class === null || $account->account_class !== 'use') {
            return null;
        }

        return $class.$element;
    }
}
