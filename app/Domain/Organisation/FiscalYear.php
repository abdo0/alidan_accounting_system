<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Organisation\Enums\FiscalYearStatus;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An accounting year and its twelve monthly periods.
 */
class FiscalYear extends Model
{
    use BelongsToCompany;

    protected $table = 'fiscal_years';

    protected $fillable = ['company_id', 'year_code', 'starts_on', 'ends_on', 'status', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'status' => FiscalYearStatus::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return HasMany<AccountingPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }
}
