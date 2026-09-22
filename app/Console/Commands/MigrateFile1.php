<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\ControlRunner;
use App\Domain\Migration\MigrationImporter;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Loads the authoritative journal workbook (M18). Always run --dry-run first: it
 * reports unknown accounts, unbalanced rows and counterparty names that need a
 * mapping, and writes nothing but the run record. --commit loads, then measures
 * the thirty controls.
 */
#[Signature('shh:migrate:file1 {path : The authoritative workbook} {--commit : Load the ledger (default is a dry run)} {--user= : Email of the Finance Manager running it} {--signoff= : External sign-off reference, required to commit} {--mapping= : CSV of source name,counterparty code (or NEW:code)} {--sheet= : Journal sheet name}')]
#[Description('Import the authoritative SHH-01 journal ledger and run the migration controls')]
class MigrateFile1 extends Command
{
    public function handle(MigrationImporter $importer, ControlRunner $controls): int
    {
        $user = User::query()->where('email', (string) $this->option('user'))->first();

        if ($user === null) {
            $this->error('Name the Finance Manager running the migration with --user=<email>.');

            return self::FAILURE;
        }

        DatabaseContext::actingAs($user->id, 'Data migration');

        $result = $importer->import($user, (string) $this->argument('path'), (bool) $this->option('commit'), $this->option('signoff'), $this->option('mapping'), $this->option('sheet'));

        $this->info(sprintf('%s: %d source rows, run %s (%s).', $result['run']->mode, $result['rows'], $result['run']->run_ref, $result['run']->status));

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($result['unresolved'] !== []) {
            $this->warn(count($result['unresolved']).' counterparty name(s) need a mapping:');
            foreach ($result['unresolved'] as $name) {
                $this->line('  '.$name);
            }
        }

        if ($result['run']->mode === 'commit' && $result['run']->status === 'completed') {
            $rows = array_map(fn ($c): array => [$c->control_code, $c->control_name, $c->source_value, $c->system_value, strtoupper($c->status)], $controls->run($result['run']));
            $this->table(['Control', 'Measure', 'Source', 'System', 'Status'], $rows);
        }

        return $result['run']->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
