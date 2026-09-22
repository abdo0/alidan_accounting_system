<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Organisation\Enums\PeriodStatus;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly accounting period and its lock state (M15).
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year_id
 * @property string $period_code
 * @property int $period_no
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property PeriodStatus $status
 */
class AccountingPeriod extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'period_code', 'period_no', 'starts_on', 'ends_on', 'status',
        'soft_closed_by', 'soft_closed_at', 'final_closed_by', 'final_closed_at',
        'locked_by', 'locked_at', 'reopened_by', 'reopened_at', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PeriodStatus::class,
            'period_no' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'soft_closed_at' => 'immutable_datetime',
            'final_closed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
        ];
    }

    /** A new period gets its closing checklist as soon as it exists. */
    protected static function booted(): void
    {
        static::created(fn (self $period) => ChecklistTask::instantiateFor($period));
    }

    /** The period a posting date falls in, if the calendar has one. */
    public static function containing(int $companyId, DateTimeInterface $date): ?self
    {
        $day = $date->format('Y-m-d');

        return self::query()
            ->where('company_id', $companyId)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->first();
    }

    public function contains(DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $this->starts_on->toDateString() <= $day && $this->ends_on->toDateString() >= $day;
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
