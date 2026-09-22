<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Enums\CostCenterType;
use App\Domain\MasterData\Enums\ProjectType;
use App\Domain\MasterData\Project;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/** Projects (Document C tab 05) and cost centres (tab 06). */
class DimensionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();

        foreach (SpecCsv::rows('05_Projects', ['Code', 'Project (EN)', 'Project (AR)', 'Type', 'Status']) as $row) {
            Project::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['Code']],
                [
                    'name' => $row['Project (EN)'],
                    'name_ar' => $row['Project (AR)'],
                    'project_type' => ProjectType::from(strtolower($row['Type'])),
                    'is_active' => $row['Status'] === 'Active',
                ],
            );
        }

        foreach (SpecCsv::rows('06_Cost_Centers', ['Code', 'Cost Center (EN)', 'Cost Center (AR)', 'Type']) as $row) {
            CostCenter::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['Code']],
                [
                    'name' => $row['Cost Center (EN)'],
                    'name_ar' => $row['Cost Center (AR)'],
                    'cc_type' => match ($row['Type']) {
                        'SG&A' => CostCenterType::Sga,
                        'Operations' => CostCenterType::Operations,
                        default => CostCenterType::Project,
                    },
                    'is_active' => true,
                ],
            );
        }
    }
}
