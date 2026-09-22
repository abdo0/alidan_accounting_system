<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Work breakdown under a contract.
 */
class WorkPackage extends Model implements HasDisplayName
{
    use HasTranslatableName;

    protected $table = 'work_packages';

    protected $fillable = ['contract_id', 'code', 'name', 'name_ar', 'project_id', 'cost_center_id', 'budget_value'];

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }
}
