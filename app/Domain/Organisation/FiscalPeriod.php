<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fiscal_year_id
 * @property int $entity_id
 * @property int $period_no
 * @property string $name
 * @property string|null $name_ar
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_adjustment_period
 * @property string $status
 */
class FiscalPeriod extends Model
{
    use HasTranslatableName;

    public const OPEN = 'open';

    public const SOFT_CLOSED = 'soft_closed';

    public const CLOSED = 'closed';

    public const PERMANENTLY_CLOSED = 'permanently_closed';

    protected $fillable = [
        'fiscal_year_id', 'entity_id', 'period_no', 'name', 'name_ar',
        'starts_on', 'ends_on', 'is_adjustment_period', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_adjustment_period' => 'boolean',
            'closed_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function acceptsPostings(): bool
    {
        return $this->status === self::OPEN;
    }

    /** A soft-closed period still accepts GL adjustments from a privileged user. */
    public function acceptsPrivilegedPostings(): bool
    {
        return in_array($this->status, [self::OPEN, self::SOFT_CLOSED], true);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }
}
