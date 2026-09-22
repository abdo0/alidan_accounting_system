<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Enums\NormalBalance;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account of the approved SHH-01 chart (Document C tab 04), or one of the 15
 * group codes the chart names as parents.
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $name_ar
 * @property AccountType $account_type
 * @property int $account_level
 * @property bool $is_group
 * @property bool $is_posting
 * @property bool $is_active
 * @property NormalBalance $normal_balance
 * @property string|null $fs_line_code
 * @property bool $is_cash_account
 * @property bool $is_advance_account
 * @property bool $is_control_account
 * @property string|null $control_subledger
 * @property string|null $subledger
 * @property bool $requires_counterparty
 * @property bool $requires_advance_holder
 * @property bool $requires_contract
 * @property int|null $holder_counterparty_id
 * @property int|null $responsibility_center_id
 */
class Account extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'accounts';

    protected $fillable = [
        'company_id', 'code', 'parent_id', 'name', 'name_ar', 'account_type', 'account_level',
        'is_group', 'is_posting', 'is_active', 'normal_balance', 'fs_line_code', 'reporting_group',
        'is_cash_account', 'is_advance_account', 'is_control_account', 'control_subledger', 'subledger',
        'requires_counterparty', 'requires_advance_holder', 'requires_contract', 'purpose',
        'holder_counterparty_id', 'responsibility_center_id',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'account_level' => 'integer',
            'is_group' => 'boolean',
            'is_posting' => 'boolean',
            'is_active' => 'boolean',
            'is_cash_account' => 'boolean',
            'is_advance_account' => 'boolean',
            'is_control_account' => 'boolean',
            'requires_counterparty' => 'boolean',
            'requires_advance_holder' => 'boolean',
            'requires_contract' => 'boolean',
        ];
    }

    /** VR-04: only a Posting and Active account accepts a line. */
    public function acceptsPostings(): bool
    {
        return $this->is_posting && $this->is_active && ! $this->is_group;
    }

    public function label(): string
    {
        return $this->code.' — '.$this->displayName();
    }

    /** @param  Builder<Account>  $query */
    public function scopePostable(Builder $query): void
    {
        $query->where('is_posting', true)->where('is_active', true)->where('is_group', false);
    }

    /** @return BelongsTo<Account, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Account, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'holder_counterparty_id');
    }

    /** @return BelongsTo<ResponsibilityCenter, $this> */
    public function responsibilityCenter(): BelongsTo
    {
        return $this->belongsTo(ResponsibilityCenter::class, 'responsibility_center_id');
    }
}
