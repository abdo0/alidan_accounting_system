<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\Organisation\Company;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RC-01, RC-02 and RC-CORP (Document C tab 07).
 */
class ResponsibilityCenter extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'responsibility_centers';

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'linked_counterparty_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function linkedCounterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'linked_counterparty_id');
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'responsibility_center_id');
    }
}
