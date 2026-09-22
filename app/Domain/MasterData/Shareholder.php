<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shareholder and the three accounts that carry their claims (Document C tab 08).
 */
class Shareholder extends Model
{
    protected $table = 'shareholders';

    protected $fillable = ['counterparty_id', 'code', 'loan_account_id', 'current_account_id', 'capital_account_id', 'ownership_pct', 'is_approved_financier'];

    protected function casts(): array
    {
        return [
            'is_approved_financier' => 'boolean',
        ];
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'loan_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'capital_account_id');
    }
}
