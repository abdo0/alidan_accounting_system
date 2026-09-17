<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $form_no
 * @property string $statement_group
 * @property int|null $analytical_no
 * @property array<int, string>|null $entity_types
 * @property bool $requires_cost_centres
 * @property string|null $awaiting_module
 * @property bool $is_active
 */
class StatementDefinition extends Model
{
    use HasTranslatableName;

    protected $fillable = [
        'code', 'name_ar', 'name_en', 'form_no', 'statement_group', 'analytical_no',
        'entity_types', 'requires_cost_centres', 'awaiting_module', 'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'entity_types' => 'array',
            'requires_cost_centres' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StatementLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class)->orderBy('sequence');
    }

    /** HasTranslatableName resolves `name`; the UAS names are Arabic-native. */
    public function getNameAttribute(): string
    {
        return $this->name_en ?? $this->name_ar;
    }

    /** True where the statement's data source is a subledger that does not exist yet. */
    public function isAwaitingData(): bool
    {
        return $this->awaiting_module !== null;
    }
}
