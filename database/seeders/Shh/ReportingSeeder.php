<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Account;
use App\Domain\Organisation\Company;
use App\Domain\Reporting\FsLine;
use App\Domain\Reporting\ReportDefinition;
use App\Domain\Reporting\ReportMapping;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Statement lines (Document C tab 14), the account mapping (tab 15), the report
 * catalogue (tab 16) and the filter catalogue (tab 17), all effective from the
 * accounting start (MIG-19).
 */
class ReportingSeeder extends Seeder
{
    public function run(): void
    {
        $from = Company::current()->accounting_start;

        foreach (SpecCsv::rows('14_FS_Lines', ['FS Line ID', 'Statement', 'Line (EN)', 'Line (AR)', 'Account selector', 'Sign', 'Display order', 'Subtotal of']) as $row) {
            $selector = $row['Account selector'];
            [$type, $computed] = match (true) {
                $selector === 'SUBTOTAL' => ['subtotal', null],
                $selector === 'CONTROL' => ['control', null],
                str_starts_with($selector, 'COMPUTED') => ['computed', 'PL-900'],
                (bool) preg_match('/^PL-\d{3}$/', $selector) => ['computed', $selector],
                default => ['accounts', null],
            };

            FsLine::query()->updateOrCreate(['code' => $row['FS Line ID']], [
                'statement' => $row['Statement'],
                'name' => $row['Line (EN)'],
                'name_ar' => $row['Line (AR)'],
                'line_type' => $type,
                'account_selector' => $type === 'accounts' ? $selector : null,
                'sign' => (int) $row['Sign'],
                'display_order' => (int) $row['Display order'],
                'subtotal_of' => $row['Subtotal of'] ?: null,
                'computed_from' => $computed,
                'effective_from' => $from,
            ]);
        }

        $accounts = Account::query()->pluck('id', 'code');

        foreach (SpecCsv::rows('15_Account_FS_Map', ['Account Code', 'FS Line ID', 'Statement']) as $row) {
            $accountId = $accounts[$row['Account Code']] ?? throw new \RuntimeException("Mapped account {$row['Account Code']} is not in the chart.");

            $exists = ReportMapping::query()
                ->where('account_id', $accountId)
                ->where('statement', $row['Statement'])
                ->exists();

            if (! $exists) {
                ReportMapping::query()->create([
                    'account_id' => $accountId,
                    'fs_line_code' => $row['FS Line ID'],
                    'statement' => $row['Statement'],
                    'effective_from' => $from,
                    'approval_ref' => 'Document C 15_Account_FS_Map',
                ]);
            }
        }

        foreach (SpecCsv::rows('16_Report_Catalogue', ['Report ID', 'Report (EN)', 'التقرير (عربي)', 'Module', 'Content', 'Filters', 'Source entities', 'Export', 'Phase']) as $row) {
            ReportDefinition::query()->updateOrCreate(['code' => $row['Report ID']], [
                'name' => $row['Report (EN)'],
                'name_ar' => $row['التقرير (عربي)'],
                'module' => $row['Module'],
                'content' => $row['Content'],
                'filters' => $row['Filters'],
                'source_entities' => $row['Source entities'],
                'export' => $row['Export'],
                'phase' => $row['Phase'],
            ]);
        }

        foreach (SpecCsv::rows('17_Report_Filters', ['Filter ID', 'Filter (EN)', 'المرشّح (عربي)', 'Type', 'Applies to', 'Bound field']) as $row) {
            DB::table('report_filters')->updateOrInsert(['code' => $row['Filter ID']], [
                'name' => $row['Filter (EN)'],
                'name_ar' => $row['المرشّح (عربي)'],
                'filter_type' => $row['Type'],
                'applies_to' => $row['Applies to'],
                'bound_field' => $row['Bound field'],
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
