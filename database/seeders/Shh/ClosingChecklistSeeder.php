<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Organisation\AccountingPeriod;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The 25-task monthly closing checklist (Document A §13), then one instance per
 * period from the accounting start onward (MIG-21).
 */
class ClosingChecklistSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SpecCsv::rows('closing_tasks', ['Task No', 'Task (AR)', 'Task (EN)', 'Source']) as $row) {
            DB::table('closing_task_templates')->updateOrInsert(['task_no' => (int) $row['Task No']], [
                'name' => $row['Task (EN)'],
                'name_ar' => $row['Task (AR)'],
                'source' => $row['Source'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (AccountingPeriod::query()->orderBy('starts_on')->get() as $period) {
            ChecklistTask::instantiateFor($period);
        }
    }
}
