<?php

declare(strict_types=1);

namespace App\Domain\Closing;

use App\Domain\Closing\Enums\ChecklistStatus;
use App\Domain\Organisation\AccountingPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One of the 25 monthly closing tasks for one period (Document A §13).
 *
 * @property int $id
 * @property int $period_id
 * @property int $task_no
 * @property string $name
 * @property string $name_ar
 * @property ChecklistStatus $status
 * @property CarbonImmutable|null $due_date
 * @property int|null $completed_by
 */
class ChecklistTask extends Model
{
    protected $table = 'closing_checklist';

    protected $fillable = [
        'period_id', 'task_no', 'name', 'name_ar', 'responsible_id', 'reviewer_id', 'due_date', 'status',
        'completed_by', 'completed_at', 'review_date', 'comments',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChecklistStatus::class,
            'due_date' => 'immutable_date',
            'review_date' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }

    /** Instantiates the template for a period, once. */
    public static function instantiateFor(AccountingPeriod $period): void
    {
        foreach (DB::table('closing_task_templates')->orderBy('task_no')->get() as $template) {
            self::query()->firstOrCreate(
                ['period_id' => $period->id, 'task_no' => $template->task_no],
                ['name' => $template->name, 'name_ar' => $template->name_ar, 'due_date' => $period->ends_on->addDays(10), 'status' => ChecklistStatus::Pending],
            );
        }
    }

    /** @return BelongsTo<AccountingPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
