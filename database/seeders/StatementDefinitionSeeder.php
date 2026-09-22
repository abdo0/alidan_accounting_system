<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Reporting\StatementDefinition;
use App\Domain\Reporting\StatementLine;
use Illuminate\Database\Seeder;

/**
 * The statements prescribed by الفصل الرابع of النظام المحاسبي الموحد.
 *
 * Nine primary statements and 26 analytical ones. Line-to-account mappings use chart
 * code PREFIXES, so `41` gathers 411..417 and every level beneath -- the aggregation
 * the standard's decimal numbering exists to enable.
 *
 * Statements whose data comes from a subledger that is not built yet carry
 * `awaiting_module`; they render with a visible note rather than silent zeros.
 */
class StatementDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $this->primary();
        $this->analytical();

        $this->command->info(sprintf(
            'Statements seeded: %d primary, %d analytical.',
            StatementDefinition::where('statement_group', 'primary')->count(),
            StatementDefinition::where('statement_group', 'analytical')->count(),
        ));
    }

    // ------------------------------------------------------------------ primary

    private function primary(): void
    {
        $this->balanceSheet();
        $this->productionTrading();
        $this->revenueExpense();
        $this->contractProfitLoss();
        $this->currentOperations();
        $this->cashFlow();
        $this->grossValueAdded();
        $this->grossValueAddedDistribution();
        $this->trialBalance();
    }

    /** الميزانية العامة — printed pages 257-258. Vertical; contras sit BELOW the totals. */
    private function balanceSheet(): void
    {
        $this->define('BALANCE_SHEET', 'الميزانية العامة', 'General Balance Sheet', 'primary', [
            'display_order' => 1,
        ], [
            ['h', 'الموجودات', 'Assets'],
            ['a', 'الموجودات الثابتة (بالقيمة الدفترية)', 'Fixed assets at book value', ['11'], 1, 1],
            ['a', 'مشروعات تحت التنفيذ', 'Projects under execution', ['12'], 3, 1],
            ['a', 'قروض ممنوحة', 'Loans granted', ['14'], 4, 1],
            ['a', 'استثمارات مالية', 'Financial investments', ['15'], 5, 1],
            ['a', 'المخزون', 'Inventory', ['13'], 6, 1],
            ['a', 'المدينون', 'Debtors', ['16'], 7, 1],
            ['a', 'النقود', 'Cash', ['18'], 8, 1],
            ['t', 'مجموع الموجودات', 'Total assets', null, null, 0, 'L20+L30+L40+L50+L60+L70+L80'],
            ['s2', '', ''],
            ['h', 'مصادر التمويل', 'Sources of finance'],
            ['a', 'رأس المال', 'Capital', ['21'], null, 1, null, -1],
            ['a', 'الاحتياطيات', 'Reserves', ['22'], 9, 1, null, -1],
            ['a', 'التخصيصات', 'Provisions', ['23'], 10, 1, null, -1],
            ['a', 'القروض المستلمة', 'Loans received', ['24'], 4, 1, null, -1],
            ['a', 'المصارف الدائنة', 'Banks (credit)', ['25'], null, 1, null, -1],
            ['a', 'الدائنون', 'Creditors', ['26'], 11, 1, null, -1],
            ['a', 'حساب العمليات الجارية', 'Current operations account', ['28'], null, 1, null, -1],
            ['t', 'مجموع مصادر التمويل', 'Total sources of finance', null, null, 0, 'L120+L130+L140+L150+L160+L170+L180'],
            ['s2', '', ''],
            // Presented below the totals, outside them: "لا يظهر لهما رصيد في الميزانية".
            ['h', 'الحسابات المتقابلة', 'Contra accounts'],
            ['a', 'الحسابات المتقابلة المدينة', 'Contra accounts — debit', ['19'], 21, 1],
            ['a', 'الحسابات المتقابلة الدائنة', 'Contra accounts — credit', ['29'], 21, 1, null, -1],
        ]);
    }

    /** حساب الإنتاج والمتاجرة والأرباح والخسائر والتوزيع — إنموذج رقم (١), pages 259-260. */
    private function productionTrading(): void
    {
        $this->define('PRODUCTION_TRADING', 'حساب الإنتاج والمتاجرة والأرباح والخسائر والتوزيع', 'Production, Trading, Profit & Loss and Distribution Account', 'primary', [
            'form_no' => '١',
            'entity_types' => ['industrial', 'commercial', 'service'],
            'requires_cost_centres' => true,
            'display_order' => 2,
        ], [
            ['a', 'إيرادات النشاط الجاري', 'Current activity revenue', ['41', '42', '43', '44', '45'], 17, 0, null, -1],
            ['h', 'ينزل: كلفة النشاط الجاري', 'Less: cost of current activity'],
            ['a', 'المستلزمات السلعية', 'Commodity requisites', ['32'], 14, 1],
            ['a', 'المستلزمات الخدمية', 'Service requisites', ['33'], 15, 1],
            ['a', 'مقاولات وخدمات', 'Contracts and services', ['34'], 16, 1],
            ['a', 'مشتريات البضائع والأراضي بغرض البيع', 'Purchases for resale', ['35'], 16, 1],
            ['a', 'الرواتب والأجور', 'Salaries and wages', ['31'], 13, 1],
            ['a', 'الاندثارات', 'Depreciation', ['37'], 1, 1],
            ['t', 'صافي كلفة النشاط الجاري', 'Net cost of current activity', null, null, 0, 'L30+L40+L50+L60+L70+L80'],
            ['t', 'فائض (عجز) النشاط الجاري', 'Current activity surplus (deficit)', null, null, 0, 'L10-L90'],
            ['s2', '', ''],
            ['a', 'يضاف: فوائد وإيجارات أراضي دائنة', 'Add: interest and land rents received', ['46'], null, 1, null, -1],
            ['a', 'يضاف: الإعانات', 'Add: subsidies', ['47'], null, 1, null, -1],
            ['a', 'يضاف: الإيرادات التحويلية', 'Add: transfer revenue', ['48'], 18, 1, null, -1],
            ['a', 'يضاف: الإيرادات الأخرى', 'Add: other revenue', ['49'], 18, 1, null, -1],
            ['a', 'تنزل: فوائد مدينة واستئجار الأراضي', 'Less: interest paid and land rental', ['36'], null, 1],
            ['a', 'تنزل: المصروفات التحويلية', 'Less: transfer expenditure', ['38'], 16, 1],
            ['a', 'تنزل: المصروفات الأخرى', 'Less: other expenses', ['39'], 16, 1],
            ['t', 'الفائض (العجز)', 'Surplus (deficit)', null, null, 0, 'L100+L110+L120+L130+L140-L150-L160-L170'],
        ]);
    }

    /** حساب الإيرادات والمصروفات والتوزيع — إنموذج رقم (٢), pages 261-262. */
    private function revenueExpense(): void
    {
        $this->define('REVENUE_EXPENSE', 'حساب الإيرادات والمصروفات والتوزيع', 'Revenue and Expenditure and Distribution Account', 'primary', [
            'form_no' => '٢',
            'entity_types' => ['service'],
            'display_order' => 3,
        ], [
            ['h', 'إيرادات النشاط الجاري', 'Current activity revenue'],
            ['a', 'إيراد النشاط السلعي', 'Commodity activity revenue', ['41'], 17, 1, null, -1],
            ['a', 'إيراد النشاط التجاري', 'Trading revenue', ['42'], 17, 1, null, -1],
            ['a', 'إيراد النشاط الخدمي', 'Service revenue', ['43'], 17, 1, null, -1],
            ['a', 'إيراد التشغيل للغير', 'Revenue from operating for others', ['44'], 17, 1, null, -1],
            ['a', 'كلفة الموجودات المصنعة داخلياً', 'Cost of internally manufactured assets', ['45'], 17, 1, null, -1],
            ['a', 'فوائد وإيجارات أراضي دائنة', 'Interest and land rents received', ['46'], null, 1, null, -1],
            ['a', 'الإعانات', 'Subsidies', ['47'], null, 1, null, -1],
            ['t', 'مجموع الإيرادات', 'Total revenue', null, null, 0, 'L20+L30+L40+L50+L60+L70+L80'],
            ['s2', '', ''],
            ['h', 'تنزل مصروفات النشاط الجاري', 'Less: current activity expenditure'],
            ['a', 'الرواتب والأجور', 'Salaries and wages', ['31'], 13, 1],
            ['a', 'المستلزمات السلعية', 'Commodity requisites', ['32'], 14, 1],
            ['a', 'المستلزمات الخدمية', 'Service requisites', ['33'], 15, 1],
            ['a', 'مقاولات وخدمات', 'Contracts and services', ['34'], 16, 1],
            ['a', 'مشتريات البضائع والأراضي بغرض البيع', 'Purchases for resale', ['35'], 16, 1],
            ['a', 'فوائد مدينة واستئجار الأراضي', 'Interest paid and land rental', ['36'], null, 1],
            ['a', 'الاندثارات', 'Depreciation', ['37'], 1, 1],
            ['t', 'مجموع المصروفات', 'Total expenditure', null, null, 0, 'L120+L130+L140+L150+L160+L170+L180'],
            ['t', 'فائض (عجز) النشاط الجاري', 'Current activity surplus (deficit)', null, null, 0, 'L100-L190'],
            ['s2', '', ''],
            ['a', 'تضاف: الإيرادات التحويلية', 'Add: transfer revenue', ['48'], 18, 1, null, -1],
            ['a', 'تضاف: الإيرادات الأخرى', 'Add: other revenue', ['49'], 18, 1, null, -1],
            ['a', 'تنزل: المصروفات التحويلية', 'Less: transfer expenditure', ['38'], 16, 1],
            ['a', 'تنزل: المصروفات الأخرى', 'Less: other expenses', ['39'], 16, 1],
            ['t', 'زيادة (نقص) الإيرادات على المصروفات', 'Excess of revenue over expenditure', null, null, 0, 'L200+L220+L230-L240-L250'],
        ]);
    }

    /** حساب الأرباح والخسائر للتعهدات والمقاولات المنجزة — pages 263-264. */
    private function contractProfitLoss(): void
    {
        $this->define('CONTRACT_PL', 'حساب الأرباح والخسائر للتعهدات والمقاولات المنجزة', 'Profit and Loss for Completed Undertakings and Contracts', 'primary', [
            'entity_types' => ['contracting'],
            'awaiting_module' => 'projects',
            'display_order' => 4,
        ], [
            ['h', 'المشاريع المنجزة', 'Completed projects'],
            ['n', 'يُعرض هذا الكشف مشروعاً مشروعاً بالإيرادات والمصروفات والربح (الخسارة)', 'Presented project by project: revenue, expenditure and profit (loss)'],
            ['s2', '', ''],
            ['a', 'تنزل: فوائد مدينة واستئجار أراضي', 'Less: interest paid and land rental', ['36'], null, 1],
            ['a', 'تنزل: المصروفات التحويلية', 'Less: transfer expenditure', ['38'], 16, 1],
            ['a', 'تنزل: المصروفات الأخرى', 'Less: other expenses', ['39'], 16, 1],
            ['a', 'تضاف: إيرادات النشاط التجاري والخدمي', 'Add: trading and service revenue', ['42', '43', '44'], 17, 1, null, -1],
            ['a', 'تضاف: الإيرادات التحويلية والأخرى', 'Add: transfer and other revenue', ['48', '49'], 18, 1, null, -1],
            ['t', 'المجموع الكلي', 'Grand total', null, null, 0, 'L70+L80-L40-L50-L60'],
        ]);
    }

    /** كشف العمليات الجارية — pages 265-266. Two stages; 384 sits in stage 1. */
    private function currentOperations(): void
    {
        $this->define('CURRENT_OPERATIONS', 'كشف العمليات الجارية', 'Statement of Current Operations', 'primary', [
            'display_order' => 5,
        ], [
            ['h', 'المرحلة الأولى — الإيرادات الجارية', 'Stage one — current revenue'],
            ['a', 'إيرادات النشاط الجاري', 'Current activity revenue', ['41', '42', '43', '44', '45'], 17, 1, null, -1],
            ['a', 'فوائد وإيجار أراضي دائنة', 'Interest and land rent received', ['461', '462'], null, 1, null, -1],
            ['a', 'الإعانات', 'Subsidies', ['47'], null, 1, null, -1],
            ['a', 'حسابات النتيجة المتقابلة الدائنة', 'Credit contra result accounts', ['294'], 21, 1, null, -1],
            ['t', 'مجموع الإيرادات الجارية', 'Total current revenue', null, null, 0, 'L20+L30+L40+L50'],
            ['s2', '', ''],
            ['h', 'المصروفات الجارية', 'Current expenditure'],
            ['a', 'الرواتب والأجور', 'Salaries and wages', ['31'], 13, 1],
            ['a', 'المستلزمات السلعية', 'Commodity requisites', ['32'], 14, 1],
            ['a', 'المستلزمات الخدمية', 'Service requisites', ['33'], 15, 1],
            ['a', 'مقاولات وخدمات ومشتريات بغرض البيع', 'Contracts, services and purchases for resale', ['34', '35'], 16, 1],
            ['a', 'فوائد مدينة واستئجار الأراضي', 'Interest paid and land rental', ['36'], null, 1],
            ['a', 'الاندثارات', 'Depreciation', ['37'], 1, 1],
            // Taxes and fees belong to stage one; the rest of 38 falls to stage two.
            ['a', 'الضرائب والرسوم', 'Taxes and fees', ['384'], 16, 1],
            ['t', 'مجموع المصروفات الجارية', 'Total current expenditure', null, null, 0, 'L90+L100+L110+L120+L130+L140+L150'],
            ['t', 'فائض (عجز) العمليات الجارية — المرحلة الأولى', 'Current operations surplus (deficit) — stage one', null, null, 0, 'L60-L160'],
            ['s2', '', ''],
            ['h', 'المرحلة الثانية', 'Stage two'],
            ['a', 'حسابات النتيجة المتقابلة المدينة', 'Debit contra result accounts', ['194'], 21, 1],
            ['a', 'تضاف: الإيرادات التحويلية والأخرى', 'Add: transfer and other revenue', ['463', '48', '49'], 18, 1, null, -1],
            ['a', 'تنزل: المصروفات التحويلية (عدا ٣٨٤) والأخرى', 'Less: transfer expenditure (excluding 384) and other', ['381', '382', '383', '385', '39'], 16, 1],
            ['t', 'الفائض القابل للتوزيع (صافي العجز) — المرحلة الثانية', 'Distributable surplus (net deficit) — stage two', null, null, 0, 'L170-L200+L210-L220'],
        ]);
    }

    /** كشف التدفق النقدي — pages 267-268. */
    private function cashFlow(): void
    {
        $this->define('CASH_FLOW', 'كشف التدفق النقدي', 'Cash Flow Statement', 'primary', [
            'display_order' => 6,
        ], [
            ['h', 'التدفقات النقدية من النشاطات التشغيلية', 'Cash flows from operating activities'],
            ['a', 'النقد المستلم من إيرادات النشاط الجاري', 'Cash received from current activity revenue', ['41', '42', '43', '44'], null, 1, null, -1],
            ['a', 'الإيرادات التحويلية والأخرى', 'Transfer and other revenue', ['48', '49'], null, 1, null, -1],
            ['a', 'تنزل: الاستخدامات', 'Less: uses', ['31', '32', '33', '34', '35'], null, 1],
            ['a', 'تنزل: المصروفات التحويلية والأخرى', 'Less: transfer and other expenditure', ['38', '39'], null, 1],
            ['t', 'صافي التدفق النقدي عن الأنشطة التشغيلية', 'Net cash flow from operating activities', null, null, 0, 'L20+L30-L40-L50'],
            ['s2', '', ''],
            ['h', 'التدفق النقدي عن الأنشطة الاستثمارية', 'Cash flow from investing activities'],
            ['a', 'الموجودات الثابتة ومشروعات تحت التنفيذ', 'Fixed assets and projects under execution', ['11', '12'], null, 1],
            ['a', 'الاستثمارات المالية والقروض الممنوحة', 'Financial investments and loans granted', ['14', '15'], null, 1],
            ['t', 'صافي التدفق النقدي عن الأنشطة الاستثمارية', 'Net cash flow from investing activities', null, null, 0, '-L90-L100'],
            ['s2', '', ''],
            ['h', 'التدفق النقدي عن الأنشطة التمويلية', 'Cash flow from financing activities'],
            ['a', 'القروض المستلمة ورأس المال', 'Loans received and capital', ['21', '24'], null, 1, null, -1],
            ['a', 'الإعانات والمنح التمويلية', 'Subsidies and financing grants', ['47'], null, 1, null, -1],
            ['t', 'صافي التدفق النقدي عن الأنشطة التمويلية', 'Net cash flow from financing activities', null, null, 0, 'L130+L140'],
            ['s2', '', ''],
            ['t', 'صافي التدفق النقدي عن الأنشطة الثلاث', 'Net cash flow from all three activities', null, null, 0, 'L60+L110+L150'],
            ['a', 'رصيد النقد كما في نهاية الفترة', 'Cash balance at period end', ['18'], 8, 0],
        ]);
    }

    /**
     * كشف إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج — page 269.
     *
     * Wages (31) and depreciation (37) are deliberately NOT inputs: they are components
     * of value added, not deductions from it.
     */
    private function grossValueAdded(): void
    {
        $this->define('GVA', 'كشف إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج', 'Gross Value Added at Factor Cost', 'primary', [
            'display_order' => 7,
        ], [
            ['h', '(١) الموارد', '(1) Resources'],
            ['a', 'إيراد نشاط الإنتاج السلعي', 'Commodity production revenue', ['41'], null, 1, null, -1],
            ['a', 'إيراد النشاط التجاري', 'Trading revenue', ['42'], null, 1, null, -1],
            ['a', 'إيراد النشاط الخدمي', 'Service revenue', ['43'], null, 1, null, -1],
            ['a', 'إيراد التشغيل للغير', 'Revenue from operating for others', ['44'], null, 1, null, -1],
            ['a', 'كلفة الموجودات المصنعة داخلياً', 'Cost of internally manufactured assets', ['45'], null, 1, null, -1],
            ['a', 'مقابل فرق تقويم تغير مخزون الإنتاج التام', 'Contra: finished production inventory revaluation', ['2943'], 21, 1, null, -1],
            ['a', 'مقابل فرق تقويم تغير مخزون بضائع وأراضي بغرض البيع', 'Contra: goods for resale inventory revaluation', ['2944'], 21, 1, null, -1],
            ['t', 'إجمالي الموارد', 'Total resources', null, null, 0, 'L20+L30+L40+L50+L60+L70+L80'],
            ['s2', '', ''],
            ['h', '(٢) مستلزمات الإنتاج', '(2) Intermediate inputs'],
            ['a', 'المستلزمات السلعية', 'Commodity requisites', ['32'], 14, 1],
            ['a', 'المستلزمات الخدمية', 'Service requisites', ['33'], 15, 1],
            ['a', 'مقاولات وخدمات', 'Contracts and services', ['34'], 16, 1],
            ['a', 'مشتريات البضائع والأراضي بغرض البيع', 'Purchases of goods and land for resale', ['35'], 16, 1],
            ['t', 'إجمالي مستلزمات الإنتاج', 'Total intermediate inputs', null, null, 0, 'L120+L130+L140+L150'],
            ['s2', '', ''],
            ['t', '(٣) إجمالي القيمة المضافة بسعر السوق', '(3) Gross value added at market prices', null, null, 0, 'L90-L160'],
            ['a', 'تنزل: الضرائب والرسوم (غير المباشرة)', 'Less: indirect taxes and fees', ['384'], null, 1],
            ['a', 'تضاف: الإعانات', 'Add: subsidies', ['47'], null, 1, null, -1],
            ['t', '(٤) إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج', '(4) Gross value added at factor cost', null, null, 0, 'L180-L190+L200'],
        ]);
    }

    /** كشف توزيع إجمالي القيمة المضافة — page 270. Must reconcile to GVA. */
    private function grossValueAddedDistribution(): void
    {
        $this->define('GVA_DISTRIBUTION', 'كشف توزيع إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج', 'Distribution of Gross Value Added at Factor Cost', 'primary', [
            'display_order' => 8,
        ], [
            /*
             * The printed form shows only the factor shares and the total, leaving the
             * operating surplus as a balancing figure against the other statement. That
             * works on paper, where both sheets are in front of the reader; here the
             * derivation is shown so the statement stands on its own and the surplus is
             * auditable rather than asserted.
             */
            ['h', 'احتساب إجمالي القيمة المضافة', 'Derivation of gross value added'],
            ['a', 'الموارد', 'Resources', ['41', '42', '43', '44', '45', '2943', '2944'], null, 1, null, -1],
            ['a', 'تنزل: مستلزمات الإنتاج', 'Less: intermediate inputs', ['32', '33', '34', '35'], null, 1],
            ['a', 'تنزل: الضرائب والرسوم (غير المباشرة)', 'Less: indirect taxes and fees', ['384'], null, 1],
            ['a', 'تضاف: الإعانات', 'Add: subsidies', ['47'], null, 1, null, -1],
            ['f', 'إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج', 'Gross value added at factor cost', null, null, 0, 'L20-L30-L40+L50'],
            ['s2', '', ''],
            ['h', 'التوزيع على عناصر الإنتاج', 'Distribution among factors of production'],
            ['a', 'عائد العمل — رواتب وأجور', 'Return to labour — salaries and wages', ['31'], 13, 1],
            ['a', 'فوائد مدينة', 'Interest paid', ['361'], null, 1],
            ['a', 'تنزل: فوائد دائنة', 'Less: interest received', ['461'], null, 1, null, -1],
            ['a', 'إيجار الأراضي المدفوع', 'Land rent paid', ['362'], null, 1],
            ['a', 'تنزل: إيجارات أراضي دائنة', 'Less: land rents received', ['462'], null, 1, null, -1],
            ['a', 'الاندثارات', 'Depreciation', ['37'], 1, 1],
            // The balancing figure: what is left of value added after the factor shares.
            ['f', 'فائض العمليات', 'Operating surplus', null, null, 1, 'L60-L90-L100-L110-L120-L130-L140'],
            ['t', 'إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج', 'Gross value added at factor cost', null, null, 0, 'L90+L100+L110+L120+L130+L140+L150'],
        ]);
    }

    /** ميزان المراجعة — page 342. Also Chapter 10's processing control R-2. */
    private function trialBalance(): void
    {
        $this->define('TRIAL_BALANCE', 'ميزان المراجعة', 'Trial Balance', 'primary', [
            'display_order' => 9,
        ], [
            ['n', 'يُستخرج على المستوى الثاني والثالث وتجري المطابقة بينهما', 'Produced at levels two and three, and reconciled between them'],
        ]);
    }

    // --------------------------------------------------------------- analytical

    /**
     * The 26 كشوفات تحليلية, printed pages 271-306.
     *
     * Those fed by a subledger that is not built carry `awaiting_module` and render a
     * note instead of zeros -- a statement showing 0 where no data has been captured is
     * indistinguishable from one showing a true nil, and the difference matters.
     *
     * @var list<array{int, string, string, list<string>|null, string|null}>
     */
    private const ANALYTICAL = [
        [1, 'كشف الموجودات الثابتة واندثاراتها', 'Fixed assets and depreciation', ['11', '231'], 'fixed_assets'],
        [2, 'كشف النفقات الإيرادية المؤجلة', 'Deferred revenue expenditure', ['118'], null],
        [3, 'كشف مشروعات تحت التنفيذ', 'Projects under execution', ['12'], null],
        [4, 'كشف القروض المستلمة والممنوحة', 'Loans received and granted', ['14', '24'], null],
        [5, 'كشف الاستثمارات المالية', 'Financial investments', ['15'], null],
        [6, 'كشف المخزون', 'Inventory', ['13'], 'inventory'],
        [7, 'كشف المدينون', 'Debtors', ['16'], 'receivables'],
        [8, 'كشف النقود', 'Cash', ['18'], null],
        [9, 'كشف الاحتياطيات', 'Reserves', ['22'], null],
        [10, 'كشف التخصيصات', 'Appropriations and provisions', ['23'], null],
        [11, 'كشف الدائنون', 'Creditors', ['26'], 'payables'],
        [12, 'كشف دائنو توزيع الأرباح', 'Profit distribution creditors', ['268'], 'distribution'],
        [13, 'كشف الرواتب والأجور', 'Salaries and wages', ['31'], null],
        [14, 'كشف المستلزمات السلعية', 'Commodity requisites', ['32'], null],
        [15, 'كشف المستلزمات الخدمية', 'Service requisites', ['33'], null],
        [16, 'كشف الاستخدامات الأخرى', 'Other uses', ['34', '35', '36', '38', '39'], null],
        [17, 'كشف إيرادات النشاط الجاري', 'Current activity revenue', ['41', '42', '43', '44', '45'], null],
        [18, 'كشف الإيرادات الأخرى', 'Other revenue', ['46', '47', '48', '49'], null],
        [19, 'كشف توزيع الاستخدامات على مراكز الكلفة', 'Allocation of uses to cost centres', ['31', '32', '33', '34', '36', '37', '38', '39'], null],
        [20, 'كشف المزايا العينية', 'Benefits in kind', ['326'], null],
        [21, 'كشف الحسابات المتقابلة المدينة والدائنة', 'Contra accounts, debit and credit', ['19', '29'], null],
        [22, 'كشف بأرصدة حسابات الخطة الأستثمارية', 'Investment plan account balances', ['12'], 'investment_plan'],
        [23, 'كشف بالمبالغ المصروفة على المشاريع الأستثمارية', 'Amounts spent on investment projects', ['12'], 'investment_plan'],
        [24, 'كشف مقارنة الأرقام الرئيسية في الحسابات الختامية للسنوات الخمس الأخيرة', 'Five-year comparison of key closing figures', null, 'history'],
        [25, 'كشف تكوين رأس المال الثابت والإجمالي والتغيير في المخزون', 'Fixed and gross capital formation and inventory change', ['11', '12', '13'], null],
        [26, 'كشف بخلاصة التعهدات غير المنجزة', 'Summary of uncompleted undertakings', null, 'projects'],
    ];

    private function analytical(): void
    {
        foreach (self::ANALYTICAL as [$no, $nameAr, $nameEn, $codes, $awaiting]) {
            $lines = [];

            if ($awaiting !== null) {
                $lines[] = ['n', 'هذا الكشف بانتظار إنجاز وحدة المصدر', 'Awaiting its source module'];
            }

            if ($codes !== null) {
                $lines[] = ['a', $nameAr, $nameEn, $codes, null, 0];
            }

            $this->define(
                'ANALYTICAL_'.$no,
                $nameAr,
                $nameEn,
                'analytical',
                ['analytical_no' => $no, 'awaiting_module' => $awaiting, 'display_order' => 100 + $no],
                $lines,
            );
        }
    }

    // ------------------------------------------------------------------- helper

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<int, mixed>>  $lines
     */
    private function define(string $code, string $nameAr, string $nameEn, string $group, array $attributes, array $lines): void
    {
        $definition = StatementDefinition::updateOrCreate(
            ['code' => $code],
            array_merge([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'statement_group' => $group,
                'is_active' => true,
            ], $attributes),
        );

        $definition->lines()->delete();

        $types = ['h' => 'header', 'a' => 'accounts', 'f' => 'formula', 's' => 'subtotal', 't' => 'total', 'n' => 'note', 's2' => 'spacer'];
        $sequence = 0;

        foreach ($lines as $line) {
            $sequence += 10;

            StatementLine::create([
                'statement_definition_id' => $definition->id,
                'sequence' => $sequence,
                'label_ar' => $line[1],
                'label_en' => $line[2] !== '' ? $line[2] : null,
                'line_type' => $types[$line[0]],
                'account_codes' => $line[3] ?? null,
                'analytical_ref' => $line[4] ?? null,
                'indent_level' => $line[5] ?? 0,
                'formula' => $line[6] ?? null,
                'sign' => $line[7] ?? 1,
            ]);
        }
    }
}
