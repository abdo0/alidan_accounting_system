<?php

declare(strict_types=1);

return [
    /*
     * Accounts the engine itself must resolve, mapped to Iraqi Unified Accounting
     * System codes (النظام المحاسبي الموحد, Board of Supreme Audit, 2nd ed. 2011).
     *
     * Resolved by code through this map rather than hardcoded anywhere, so the chart
     * can change without touching application code.
     */
    'accounts' => [
        // الفائض المتراكم — the UAS equivalent of retained earnings. Note the standard
        // carries surplus and deficit as separate accounts rather than one signed one.
        'retained_earnings' => '224',
        'accumulated_deficit' => '225',

        // حساب النشاط الجاري — where the period result sits before distribution.
        'current_year_earnings' => '281',

        /*
         * The UAS has NO suspense account. Unposted or unresolved items go to the
         * sundry debit/credit accounts instead. The close discipline is unchanged --
         * these must be cleared before a period closes -- but the gate looks at these
         * codes rather than a 95xx range.
         */
        'sundry_debit' => '166',   // حسابات مدينة متنوعة
        'sundry_credit' => '266',  // حسابات دائنة متنوعة

        // فروقات نقدية — cash differences, the UAS home for rounding residue.
        'rounding_debit' => '16651',
        'rounding_credit' => '26681',

        // إيرادات مستلمة مقدماً — revenue received in advance. This is the account that
        // makes the ABX Motors failure (a receipt issued with nothing in the books)
        // structurally impossible.
        'customer_deposits' => '2662',

        // Control accounts, written only by their subledger.
        'trade_receivables' => '161',   // مدينون تجاريون
        'trade_payables' => '261',      // مجهزون تجاريون
        'inventory' => '13',            // المخزون
        'cash_on_hand' => '181',        // نقدية بالصندوق
        'cash_at_bank' => '183',        // نقدية لدى المصارف
    ],

    /*
     * Uses (class 3) normally require a cost centre. The standard exempts element 35
     * (مشتريات البضائع والأراضي بغرض البيع) entirely -- it is carried straight to the
     * trading account and added back when reconciling total uses -- so a blanket
     * "every expense needs a cost centre" rule would reject conformant behaviour.
     */
    'cost_centre_exempt_prefixes' => ['35'],

    /*
     * Element 34 (مقاولات وخدمات) is allocated only to production centres.
     */
    'production_only_prefixes' => ['34'],

    /*
     * Cost-centre control classes. The UAS reserves the top five chart classes for
     * them, which is what makes the composite 531-style statutory code derivable:
     * <control class><use element>.
     */
    'cost_centre_classes' => [
        'production' => 5,     // مراقبة مراكز الإنتاج
        'prod_service' => 6,   // مراقبة مراكز الخدمات الإنتاجية
        'marketing' => 7,      // مراقبة مراكز الخدمات التسويقية
        'admin' => 8,          // مراقبة مراكز الخدمات الإدارية
        'capital' => 9,        // مراقبة مراكز العمليات الرأسمالية
    ],

    /*
     * كشف إجمالي القيمة المضافة — the Gross Value Added statement, printed page 269.
     * Wages (31) and depreciation (37) are deliberately absent from inputs: they are
     * components of value added, not deductions from it.
     */
    'value_added' => [
        'resources' => ['41', '42', '43', '44', '45', '2943', '2944'],
        'intermediate_inputs' => ['32', '33', '34', '35'],
        'indirect_taxes' => ['384'],
        'subsidies' => ['47'],
        'distribution' => [
            'labour' => ['31'],
            'interest_paid' => ['361'],
            'interest_received' => ['461'],
            'land_rent_paid' => ['362'],
            'land_rent_received' => ['462'],
            'depreciation' => ['37'],
        ],
    ],

    'currency' => [
        'default' => 'IQD',
        'decimal_places' => 0,
    ],

    'fiscal' => [
        'periods_per_year' => 12,
        'adjustment_period' => 13,
    ],

    'approval_thresholds' => [
        'manual_journal' => 50_000_000,
    ],
];
