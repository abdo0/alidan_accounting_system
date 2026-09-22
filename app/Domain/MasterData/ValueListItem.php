<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * An approved value of a reference list (Document C tab 10).
 */
class ValueListItem extends Model
{
    protected $table = 'value_list_items';

    protected $fillable = ['list_code', 'code', 'value', 'value_ar', 'source_of_value', 'approval_status', 'sort_order'];

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
        ];
    }
}
