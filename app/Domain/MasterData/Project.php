<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\ProjectType;
use App\Domain\Organisation\Company;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analytical dimension: PRJ-01, PRJ-02, PRJ-03 and CORP (Document C tab 05).
 */
class Project extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    protected $table = 'projects';

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'project_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'project_type' => ProjectType::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
