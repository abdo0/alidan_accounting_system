<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Shared\Concerns\HasTranslatableName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property string $account_class
 * @property string|null $account_subtype
 * @property string $normal_balance
 * @property string $statement
 * @property string|null $cash_flow_class
 * @property bool $is_postable
 * @property bool $is_control_account
 * @property string|null $control_subledger
 * @property bool $allow_manual_entry
 * @property bool $is_reconcilable
 * @property bool $requires_cost_centre
 * @property CarbonImmutable|null $cost_centre_enforced_from
 * @property int|null $default_cost_centre_id
 * @property bool $requires_project
 * @property string|null $statutory_code
 * @property bool $is_active
 */
class Account extends Model
{
    use HasTranslatableName;

    /** Classes that appear in the income statement. */
    public const PROFIT_AND_LOSS_CLASSES = [
        'revenue', 'cost_of_sales', 'expense', 'other_income', 'other_expense', 'tax',
    ];

    public const BALANCE_SHEET_CLASSES = ['asset', 'liability', 'equity', 'clearing'];

    protected $fillable = [
        'parent_id', 'code', 'name', 'name_ar', 'account_class', 'account_subtype',
        'normal_balance', 'statement', 'cash_flow_class', 'contra_of_account_id',
        'is_postable', 'is_control_account', 'control_subledger', 'allow_manual_entry',
        'is_reconcilable', 'requires_cost_centre', 'cost_centre_enforced_from',
        'default_cost_centre_id', 'requires_project', 'currency_code',
        'statutory_code', 'display_order', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_control_account' => 'boolean',
            'allow_manual_entry' => 'boolean',
            'is_reconcilable' => 'boolean',
            'requires_cost_centre' => 'boolean',
            'requires_project' => 'boolean',
            'is_active' => 'boolean',
            'cost_centre_enforced_from' => 'immutable_date',
        ];
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

    /** @return BelongsTo<CostCentre, $this> */
    public function defaultCostCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'default_cost_centre_id');
    }

    public function isProfitAndLoss(): bool
    {
        return in_array($this->account_class, self::PROFIT_AND_LOSS_CLASSES, true);
    }

    public function isBalanceSheet(): bool
    {
        return in_array($this->account_class, self::BALANCE_SHEET_CLASSES, true);
    }

    public function isCashOrBank(): bool
    {
        return in_array($this->account_subtype, ['cash', 'bank'], true);
    }

    /**
     * Enforcement is date-based so a back-dated correction into a period that predates
     * the policy still posts, while current entries are held to the new standard.
     */
    public function requiresCostCentreOn(CarbonImmutable $date): bool
    {
        if (! $this->requires_cost_centre) {
            return false;
        }

        return $this->cost_centre_enforced_from === null
            || $date->greaterThanOrEqualTo($this->cost_centre_enforced_from);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->displayName();
    }
}
