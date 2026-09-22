<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\BankAccountType;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank account, cash box or project safe, bound to exactly one GL account (Document C tab 09). Its book balance is computed, never stored (VR-57).
 */
class BankAccount extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'bank_accounts';

    protected $fillable = ['company_id', 'code', 'account_id', 'name', 'name_ar', 'ba_type', 'project_id', 'custodian_id', 'responsible_user_id', 'bank_name', 'account_number', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ba_type' => BankAccountType::class,
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'custodian_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
