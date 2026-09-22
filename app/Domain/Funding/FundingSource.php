<?php

declare(strict_types=1);

namespace App\Domain\Funding;

use App\Domain\Funding\Enums\FundingSourceType;
use App\Domain\MasterData\Counterparty;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who provided funds (Document B §4.4) -- never the legal accounting owner, which is the credited account.
 */
class FundingSource extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'funding_sources';

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'source_type', 'counterparty_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source_type' => FundingSourceType::class,
        ];
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }
}
