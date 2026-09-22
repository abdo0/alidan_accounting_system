<?php

declare(strict_types=1);

namespace App\Domain\Funding;

use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;

/**
 * The eleven approved funding categories (Document C tab 10).
 */
class FundingCategory extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'funding_categories';

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'source_of_value', 'approval_status'];

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
        ];
    }
}
