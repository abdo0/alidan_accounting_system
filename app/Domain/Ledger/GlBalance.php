<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Organisation\FiscalPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Period movements by account and cost centre. Opening balances are deliberately
 * NOT stored: the year-end opening journal is the sole carry-forward, so an opening
 * balance is the sum of prior periods' movements within the same fiscal year. Storing
 * both would double-count, because that opening journal posts through the normal
 * posting path into period 1's movements.
 *
 * @property int $id
 * @property int $entity_id
 * @property int $fiscal_period_id
 * @property int $account_id
 * @property int|null $cost_centre_id
 * @property string $currency_code
 * @property string|numeric $period_debit
 * @property string|numeric $period_credit
 */
class GlBalance extends Model
{
    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected $table = 'gl_balances';

    protected $fillable = [
        'entity_id', 'fiscal_period_id', 'account_id', 'cost_centre_id',
        'currency_code', 'period_debit', 'period_credit',
        'functional_period_debit', 'functional_period_credit',
    ];

    protected function casts(): array
    {
        return [
            'period_debit' => 'decimal:4',
            'period_credit' => 'decimal:4',
            'functional_period_debit' => 'decimal:4',
            'functional_period_credit' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<CostCentre, $this> */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /** @return BelongsTo<FiscalPeriod, $this> */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }
}
