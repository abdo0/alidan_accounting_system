<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Funding\ChainStep;
use App\Domain\Funding\Enums\FundingSourceType;
use App\Domain\Funding\FundingCategory;
use App\Domain\Funding\FundingSource;
use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\MasterData\Shareholder;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * Funding categories and chain steps (Document C tab 10), the account pair each
 * chain step permits (Document B §2.6), and the funding sources: one per
 * shareholder plus the third-party, recovery and recycled-recovery sources that
 * Document C tab 20 names.
 */
class FundingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::current();
        $lists = collect(SpecCsv::rows('10_Value_Lists', ['Dimension / Field', 'Code', 'Value', 'Arabic', 'Source of the value']));

        foreach ($lists->where('Dimension / Field', 'Funding Category') as $row) {
            FundingCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['Code']],
                [
                    'name' => $row['Value'],
                    'name_ar' => $row['Arabic'] ?: null,
                    'source_of_value' => $row['Source of the value'],
                    'approval_status' => str_starts_with($row['Source of the value'], 'Journal only')
                        ? ApprovalStatus::Pending
                        : ApprovalStatus::Approved,
                ],
            );
        }

        $pairs = collect(SpecCsv::rows('chain_step_pairs', ['Chain Step', 'Debit selector', 'Credit selector']))->keyBy('Chain Step');
        $order = 0;

        foreach ($lists->where('Dimension / Field', 'Funding Chain Step') as $row) {
            $pair = $pairs[$row['Code']] ?? throw new \RuntimeException("No account pair for chain step {$row['Code']}.");
            [$labelEn, $labelAr] = array_map('trim', explode(' / ', $row['Value'], 2)) + [1 => null];

            ChainStep::query()->updateOrCreate(
                ['code' => $row['Code']],
                [
                    'label' => $labelEn,
                    'label_ar' => $labelAr,
                    'debit_selector' => $pair['Debit selector'],
                    'credit_selector' => $pair['Credit selector'],
                    'sort_order' => ++$order,
                ],
            );
        }

        foreach (Shareholder::query()->with('counterparty')->get() as $shareholder) {
            FundingSource::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'FS-'.$shareholder->code],
                [
                    'name' => $shareholder->counterparty->name,
                    'name_ar' => $shareholder->counterparty->name_ar,
                    'source_type' => FundingSourceType::Shareholder,
                    'counterparty_id' => $shareholder->counterparty_id,
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['FS-TP', 'Third-party funding', 'تمويل من طرف آخر', FundingSourceType::ThirdParty],
            ['FS-REC', 'Recovery / cash returned', 'استرداد ونقد معاد', FundingSourceType::Recovery],
            ['FS-RR', 'Recycled contractor recovery', 'استرداد من المقاول أعيد استخدامه', FundingSourceType::RecycledRecovery],
        ] as [$code, $name, $nameAr, $type]) {
            FundingSource::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'name_ar' => $nameAr, 'source_type' => $type, 'is_active' => true],
            );
        }
    }
}
