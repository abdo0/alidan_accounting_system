<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\ControlRunner;
use App\Domain\Migration\MigrationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shh:migrate:controls {--run= : Run reference (default: the latest committed run)}')]
#[Description('Measure the thirty migration controls (MC-01 ... MC-30) again')]
class MigrationControls extends Command
{
    public function handle(ControlRunner $controls): int
    {
        $run = MigrationRun::query()
            ->when($this->option('run'), fn ($q, $ref) => $q->where('run_ref', $ref))
            ->where('mode', 'commit')->where('status', 'completed')
            ->latest('id')->first();

        if ($run === null) {
            $this->error('No committed migration run.');

            return self::FAILURE;
        }

        $results = $controls->run($run);
        $this->table(['Control', 'Source', 'System', 'Status'], array_map(fn ($c): array => [$c->control_code, $c->source_value, $c->system_value, strtoupper($c->status)], $results));

        return collect($results)->every(fn ($c): bool => $c->status === 'pass') ? self::SUCCESS : self::FAILURE;
    }
}
