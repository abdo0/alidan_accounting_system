<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\CostCenterType;
use App\Domain\Organisation\Company;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A functional cost centre (Document C tab 06). Never a vendor.
 */
class CostCenter extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'cost_centers';

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'cc_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cc_type' => CostCenterType::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
