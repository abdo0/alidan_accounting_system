<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Ledger\Account;
use App\Domain\Ledger\Journal;
use App\Domain\Organisation\Currency;
use App\Domain\Organisation\Entity;
use App\Domain\Organisation\FiscalPeriod;
use App\Domain\Organisation\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production-safe reference data. Never seeds transactions.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessControlSeeder::class);

        $this->seedCurrency();
        $entity = $this->seedEntity();
        $this->call(ChartOfAccountsSeeder::class);
        $this->linkSystemAccounts($entity);
        $this->seedJournals();
        $this->seedCostCentres($entity);
        $this->seedFiscalYears($entity);
        $this->call(StatementDefinitionSeeder::class);
    }

    private function seedCurrency(): void
    {
        Currency::updateOrCreate(
            ['code' => 'IQD'],
            [
                'name' => 'Iraqi Dinar',
                'name_ar' => 'دينار عراقي',
                'symbol' => 'د.ع',
                // No minor unit in practice.
                'decimal_places' => 0,
                'is_active' => true,
            ]
        );
    }

    private function seedEntity(): Entity
    {
        return Entity::updateOrCreate(
            ['code' => 'ALIDAN'],
            [
                'name' => 'Al-Idan',
                'name_ar' => 'العيدان',
                'legal_name' => 'Al-Idan Company',
                'legal_name_ar' => 'شركة العيدان',
                'functional_currency' => 'IQD',
                'is_consolidation_node' => false,
                'is_active' => true,
            ]
        );
    }

    /**
     * Resolved by code through config/accounting.php so the official chart can use
     * different numbering without any code change.
     */
    private function linkSystemAccounts(Entity $entity): void
    {
        $map = config('accounting.accounts');

        $entity->update([
            // الفائض المتراكم
            'retained_earnings_account_id' => $this->accountId($map['retained_earnings']),
            // حساب النشاط الجاري
            'current_earnings_account_id' => $this->accountId($map['current_year_earnings']),
            // The UAS has no suspense account; unresolved items sit in the sundry
            // accounts, with the same "must be clear before close" discipline.
            'suspense_account_id' => $this->accountId($map['sundry_debit']),
            // فروقات نقدية
            'rounding_account_id' => $this->accountId($map['rounding_debit']),
        ]);
    }

    private function accountId(string $code): ?int
    {
        return Account::query()->where('code', $code)->value('id');
    }

    /**
     * Doc B's six books of prime entry, plus the journals every later module posts
     * through. Seeding only the six means a later module either abuses the general
     * journal or starts its gapless sequence at 1 in month seven.
     */
    private function seedJournals(): void
    {
        $journals = [
            ['SDB', 'Sales Day Book', 'دفتر يومية المبيعات', 'sales', false, false, 'SDB', 1],
            ['PDB', 'Purchases Day Book', 'دفتر يومية المشتريات', 'purchases', false, false, 'PDB', 2],
            ['RIB', 'Returns Inwards Day Book', 'دفتر مردودات المبيعات', 'returns_in', false, false, 'RIB', 3],
            ['ROB', 'Returns Outwards Day Book', 'دفتر مردودات المشتريات', 'returns_out', false, false, 'ROB', 4],
            ['CB', 'Cash Book', 'دفتر النقدية', 'cash', false, false, 'CB', 5],
            ['PCB', 'Petty Cash Book', 'دفتر المصروفات النثرية', 'petty_cash', false, false, 'PCB', 6],
            ['GJ', 'General Journal', 'دفتر اليومية العامة', 'general', true, false, 'GJ', 7],
            ['PAY', 'Payroll Journal', 'يومية الرواتب', 'payroll', false, true, 'PAY', 8],
            ['FA', 'Fixed Assets Journal', 'يومية الأصول الثابتة', 'fixed_assets', false, true, 'FA', 9],
            ['INV', 'Inventory Journal', 'يومية المخزون', 'inventory', false, true, 'INV', 10],
            ['ALC', 'Allocation Journal', 'يومية توزيع التكاليف', 'allocation', false, true, 'ALC', 11],
            ['CLO', 'Closing Journal', 'يومية الإقفال', 'closing', false, true, 'CLO', 12],
            ['OPN', 'Opening Journal', 'يومية الأرصدة الافتتاحية', 'opening', false, true, 'OPN', 13],
        ];

        foreach ($journals as [$code, $name, $nameAr, $type, $manual, $system, $prefix, $order]) {
            Journal::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'journal_type' => $type,
                    'allows_manual_entry' => $manual,
                    'is_system' => $system,
                    'sequence_prefix' => $prefix,
                    'display_order' => $order,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Three levels is almost always enough; depth beyond that usually means a second
     * dimension is being smuggled into the cost centre tree.
     */
    private function seedCostCentres(Entity $entity): void
    {
        // The UAS fixes the cost-centre taxonomy and reserves chart classes 5-9 for it.
        // Two placements differ from intuition and must be seeded correctly: welfare
        // centres (canteen, clinic, housing, staff transport) are PRODUCTION-SERVICE,
        // not administrative; and the finished-goods store is MARKETING while the
        // raw-material stores are production-service.
        $tree = [
            ['ROOT', 'Al-Idan', 'العيدان', null, 'admin', 8, false, null, null],

            ['5', 'Production centres', 'مراكز الإنتاج', 'ROOT', 'production', 5, false, null, null],
            ['51', 'Main plant', 'المعمل الرئيسي', '5', 'production', 5, true, 'Operations', 'Baghdad'],

            ['6', 'Production services', 'مراكز الخدمات الإنتاجية', 'ROOT', 'prod_service', 6, false, null, null],
            ['61', 'Staff welfare', 'الخدمات الاجتماعية للعاملين', '6', 'prod_service', 6, true, 'Support', 'Baghdad'],
            ['62', 'Workshops', 'ورش التصنيع والصيانة', '6', 'prod_service', 6, true, 'Support', 'Baghdad'],
            ['63', 'Stores', 'المخازن', '6', 'prod_service', 6, true, 'Support', 'Baghdad'],
            ['65', 'Utilities', 'محطات القوى المحركة', '6', 'prod_service', 6, true, 'Support', 'Baghdad'],

            ['7', 'Marketing services', 'مراكز الخدمات التسويقية', 'ROOT', 'marketing', 7, false, null, null],
            ['71', 'Sales', 'المبيعات', '7', 'marketing', 7, true, 'Commercial', 'Baghdad'],
            ['72', 'Finished goods store', 'مخزن البضاعة الجاهزة', '7', 'marketing', 7, true, 'Commercial', 'Baghdad'],

            ['8', 'Administrative services', 'مراكز الخدمات الإدارية', 'ROOT', 'admin', 8, false, null, null],
            ['81', 'Finance', 'المالية', '8', 'admin', 8, true, 'Support', 'Baghdad'],
            ['82', 'Administration', 'الإدارة', '8', 'admin', 8, true, 'Support', 'Baghdad'],

            ['9', 'Capital operations', 'مراكز العمليات الرأسمالية', 'ROOT', 'capital', 9, false, null, null],
            ['91', 'Self-constructed assets', 'الموجودات المصنعة داخلياً', '9', 'capital', 9, true, 'Operations', 'Baghdad'],

            // Landing zone for anything arriving without a centre. An honest
            // "unassigned" beats a guessed attribution that looks complete and is wrong.
            ['900', 'Unassigned', 'غير موزع', 'ROOT', 'admin', 8, true, null, null],
        ];

        $ids = [];
        $paths = [];

        foreach ($tree as [$code, $name, $nameAr, $parentCode, $type, $controlClass, $postable, $dept, $branch]) {
            $parentId = $parentCode === null ? null : $ids[$parentCode];
            $path = $parentCode === null ? $code : $paths[$parentCode].'.'.$code;

            $centre = CostCentre::updateOrCreate(
                ['entity_id' => $entity->id, 'code' => $code],
                [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'cost_centre_type' => $type,
                    'control_class' => $controlClass,
                    'department' => $dept,
                    'responsibility_unit' => $dept,
                    'branch' => $branch,
                    'depth' => substr_count($path, '.'),
                    'is_postable' => $postable,
                    'allows_revenue' => in_array($type, ['production', 'marketing'], true),
                    'is_active' => true,
                ]
            );

            // ltree has no Eloquent cast; set it with an explicit cast.
            DB::statement('UPDATE cost_centres SET path = ?::ltree WHERE id = ?', [$path, $centre->id]);

            $ids[$code] = $centre->id;
            $paths[$code] = $path;
        }
    }

    private function seedFiscalYears(Entity $entity): void
    {
        foreach ([2026, 2027] as $year) {
            $start = CarbonImmutable::create($year, 1, 1);
            $end = $start->endOfYear()->startOfDay();

            $fiscalYear = FiscalYear::updateOrCreate(
                ['entity_id' => $entity->id, 'code' => "FY{$year}"],
                ['starts_on' => $start, 'ends_on' => $end, 'status' => 'open']
            );

            for ($month = 1; $month <= 12; $month++) {
                $periodStart = CarbonImmutable::create($year, $month, 1);

                FiscalPeriod::updateOrCreate(
                    ['fiscal_year_id' => $fiscalYear->id, 'period_no' => $month],
                    [
                        'entity_id' => $entity->id,
                        'name' => $periodStart->format('F Y'),
                        'name_ar' => $periodStart->format('m/Y'),
                        'starts_on' => $periodStart,
                        'ends_on' => $periodStart->endOfMonth()->startOfDay(),
                        'is_adjustment_period' => false,
                        'status' => 'open',
                    ]
                );
            }

            // Period 13 carries audit adjustments. Both dates are the year end: if
            // they were left unset, V-04 would reject every adjustment posted to it.
            FiscalPeriod::updateOrCreate(
                ['fiscal_year_id' => $fiscalYear->id, 'period_no' => 13],
                [
                    'entity_id' => $entity->id,
                    'name' => "Adjustments {$year}",
                    'name_ar' => "تسويات {$year}",
                    'starts_on' => $end,
                    'ends_on' => $end,
                    'is_adjustment_period' => true,
                    'status' => 'open',
                ]
            );
        }
    }
}
