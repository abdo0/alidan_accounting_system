<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name the source used for a counterparty.
 */
class CounterpartyAlias extends Model
{
    protected $table = 'counterparty_aliases';

    protected $fillable = ['counterparty_id', 'alias', 'legacy_code', 'source'];

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }
}
