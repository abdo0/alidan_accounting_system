<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\CounterpartyType;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Every external party in one master (RE-02): contractor, supplier, employee,
 * custodian, shareholder, government body. Never a cost centre.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property CounterpartyType $cp_type
 * @property bool $is_advance_holder
 * @property bool $is_shareholder
 * @property bool $is_government
 */
class Counterparty extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'counterparties';

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'cp_type', 'role_description',
        'is_contractor', 'is_supplier', 'is_advance_holder', 'is_shareholder', 'is_employee',
        'is_government', 'source_alias', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cp_type' => CounterpartyType::class,
            'is_contractor' => 'boolean',
            'is_supplier' => 'boolean',
            'is_advance_holder' => 'boolean',
            'is_shareholder' => 'boolean',
            'is_employee' => 'boolean',
            'is_government' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->code.' — '.$this->displayName();
    }

    /** @return HasMany<CounterpartyAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CounterpartyAlias::class, 'counterparty_id');
    }

    /** @return HasOne<Shareholder, $this> */
    public function shareholder(): HasOne
    {
        return $this->hasOne(Shareholder::class, 'counterparty_id');
    }

    /** @return HasMany<Account, $this> */
    public function heldAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'holder_counterparty_id');
    }
}
