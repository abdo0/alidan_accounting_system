<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\MasterData\Project;
use App\Domain\Organisation\Enums\ParameterType;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One effective-dated value of a system parameter (M00). A change is a new row;
 * history is never rewritten.
 *
 * @property int $id
 * @property string $param_code
 * @property string|null $value
 * @property ParameterType $data_type
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property int|null $project_id
 */
class Parameter extends Model implements HasDisplayName
{
    use BelongsToCompany, HasTranslatableName;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'parameters';

    protected $fillable = [
        'company_id', 'param_code', 'spec_ref', 'name', 'name_ar', 'data_type', 'value', 'project_id',
        'effective_from', 'effective_to', 'approval_ref', 'reason', 'source', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'data_type' => ParameterType::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'changed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
