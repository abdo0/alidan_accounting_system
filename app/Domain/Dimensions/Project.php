<?php

declare(strict_types=1);

namespace App\Domain\Dimensions;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $cost_centre_id
 * @property string $status
 * @property bool $is_active
 */
class Project extends Model
{
    use HasTranslatableName;

    protected $fillable = [
        'entity_id', 'code', 'name', 'name_ar', 'customer_id', 'cost_centre_id',
        'starts_on', 'ends_on', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<CostCentre, $this> */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
