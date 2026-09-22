<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property int $decimal_places
 * @property bool $is_active
 */
class Currency extends Model
{
    use HasTranslatableName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'name_ar', 'symbol', 'decimal_places', 'is_active'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'is_active' => 'boolean'];
    }
}
