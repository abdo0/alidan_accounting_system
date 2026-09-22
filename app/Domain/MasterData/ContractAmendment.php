<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\AmendmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An amendment or change order to a contract.
 */
class ContractAmendment extends Model
{
    protected $table = 'contract_amendments';

    protected $fillable = ['contract_id', 'amendment_no', 'amendment_type', 'value_change', 'approved_on', 'approval_ref', 'description'];

    protected function casts(): array
    {
        return [
            'amendment_type' => AmendmentType::class,
            'approved_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
