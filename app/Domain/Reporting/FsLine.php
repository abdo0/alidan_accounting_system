<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;

/**
 * A financial statement line (Document C tab 14). Sign is the presentation
 * multiplier applied to the natural (debit-positive) balance.
 *
 * @property int $id
 * @property string $code
 * @property string $statement
 * @property string $name
 * @property string|null $name_ar
 * @property string $line_type
 * @property string|null $account_selector
 * @property int $sign
 * @property int $display_order
 * @property string|null $subtotal_of
 * @property string|null $computed_from
 */
class FsLine extends Model implements HasDisplayName
{
    use HasTranslatableName;

    protected $table = 'fs_lines';

    protected $fillable = [
        'code', 'statement', 'name', 'name_ar', 'line_type', 'account_selector', 'sign', 'display_order',
        'subtotal_of', 'computed_from', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'sign' => 'integer',
            'display_order' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }
}
