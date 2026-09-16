<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property string $functional_currency
 * @property bool $is_consolidation_node
 * @property int|null $retained_earnings_account_id
 * @property int|null $current_earnings_account_id
 * @property int|null $suspense_account_id
 * @property int|null $rounding_account_id
 * @property bool $is_active
 */
class Entity extends Model
{
    use HasTranslatableName;

    protected $fillable = [
        'parent_id', 'code', 'name', 'name_ar', 'legal_name', 'legal_name_ar',
        'tax_registration_no', 'commercial_register_no', 'functional_currency',
        'is_consolidation_node', 'retained_earnings_account_id',
        'current_earnings_account_id', 'suspense_account_id', 'rounding_account_id',
        'address', 'phone', 'email', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_consolidation_node' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'functional_currency', 'code');
    }

    /** @return HasMany<FiscalYear, $this> */
    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    /** @return HasMany<FiscalPeriod, $this> */
    public function fiscalPeriods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }
}
