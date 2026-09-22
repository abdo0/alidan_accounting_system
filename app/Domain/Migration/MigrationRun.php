<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the importer, dry or committed, with its source hash and summary.
 *
 * @property int $id
 * @property string $run_ref
 * @property string $mode
 * @property string $status
 * @property array<string, mixed>|null $summary
 */
class MigrationRun extends Model
{
    public $timestamps = false;

    protected $table = 'migration_runs';

    protected $fillable = ['run_ref', 'source_file', 'source_sha256', 'mode', 'status', 'signoff_ref', 'run_by', 'summary', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    /** @return HasMany<MigrationControlResult, $this> */
    public function controls(): HasMany
    {
        return $this->hasMany(MigrationControlResult::class, 'migration_run_id')->orderBy('control_code');
    }
}
