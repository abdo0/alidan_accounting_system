<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The reporting entity. SHH-01 is the only row at go-live (Document C tab 20).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property string $currency
 * @property bool $decimals_allowed
 * @property CarbonImmutable $accounting_start
 */
class Company extends Model implements HasDisplayName
{
    use HasTranslatableName;

    protected $table = 'companies';

    protected $fillable = ['code', 'name', 'name_ar', 'operating_model', 'currency', 'decimals_allowed', 'accounting_start', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'decimals_allowed' => 'boolean',
            'is_active' => 'boolean',
            'accounting_start' => 'immutable_date',
        ];
    }

    /**
     * The reporting entity. SHH-01 is the only company at go-live; this is the one
     * place that assumption is written down.
     */
    public static function current(): self
    {
        return once(fn (): self => self::query()->where('is_active', true)->orderBy('id')->firstOrFail());
    }

    /** @return HasMany<FiscalYear, $this> */
    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class, 'company_id');
    }

    /** @return HasMany<AccountingPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'company_id');
    }
}
