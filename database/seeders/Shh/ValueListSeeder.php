<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\MasterData\ValueListItem;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The approved reference lists of Document C tab 10 other than the funding
 * categories and chain steps, which have tables of their own. Values the ledger
 * uses but the master list lacks load as Pending (CONF-11, EXC-SYS-02).
 */
class ValueListSeeder extends Seeder
{
    private const OWN_TABLES = ['Funding Category', 'Funding Chain Step'];

    public function run(): void
    {
        $order = [];

        foreach (SpecCsv::rows('10_Value_Lists', ['Dimension / Field', 'Code', 'Value', 'Arabic', 'Source of the value']) as $row) {
            if (in_array($row['Dimension / Field'], self::OWN_TABLES, true)) {
                continue;
            }

            $list = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($row['Dimension / Field'])), '_');
            $order[$list] = ($order[$list] ?? 0) + 1;

            ValueListItem::query()->updateOrCreate(
                ['list_code' => $list, 'value' => $row['Value']],
                [
                    'code' => $row['Code'] ?: null,
                    'value_ar' => $row['Arabic'] ?: null,
                    'source_of_value' => $row['Source of the value'],
                    'approval_status' => str_starts_with($row['Source of the value'], 'Journal only')
                        ? ApprovalStatus::Pending
                        : ApprovalStatus::Approved,
                    'sort_order' => $order[$list],
                ],
            );
        }
    }
}
