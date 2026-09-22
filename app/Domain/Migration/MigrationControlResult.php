<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use Illuminate\Database\Eloquent\Model;

/**
 * One migration control measured on one run (Document C tab 23).
 *
 * @property string $control_code
 * @property string $source_value
 * @property string $system_value
 * @property string $status
 */
class MigrationControlResult extends Model
{
    public $timestamps = false;

    protected $table = 'migration_control';

    protected $fillable = ['migration_run_id', 'control_code', 'control_name', 'source_value', 'system_value', 'status', 'notes', 'run_at'];
}
