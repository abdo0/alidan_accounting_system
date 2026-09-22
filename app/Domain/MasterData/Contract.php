<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\ContractStatus;
use App\Domain\MasterData\Enums\ContractType;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The government concession contract and commercial contracts (M13).
 *
 * @property int $id
 * @property string $contract_no
 * @property string $title
 * @property string|null $title_ar
 * @property int|null $contract_value
 * @property string|null $retention_pct
 * @property list<string>|null $pending_fields
 * @property CarbonImmutable|null $signed_on
 */
class Contract extends Model
{
    use BelongsToCompany;

    protected $table = 'contracts';

    protected $fillable = ['company_id', 'contract_no', 'title', 'title_ar', 'contract_type', 'counterparty_id', 'project_id', 'contract_value', 'signed_on', 'starts_on', 'ends_on', 'term_years', 'retention_pct', 'advance_recovery_pct', 'status', 'pending_fields', 'notes'];

    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'status' => ContractStatus::class,
            'signed_on' => 'immutable_date',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'pending_fields' => 'array',
            'contract_value' => 'integer',
        ];
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return HasMany<ContractAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id');
    }

    /** @return HasMany<WorkPackage, $this> */
    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class, 'contract_id');
    }
}
