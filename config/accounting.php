<?php

declare(strict_types=1);

return [
    /*
     * Accounts the engine itself must be able to find.
     *
     * Resolved by CODE through this map rather than hardcoded in the code, so the
     * official Iraqi Unified Accounting System chart can use entirely different
     * numbering from the placeholder without breaking closing, reversal, rounding or
     * deposit handling. Change the values here when the real chart is loaded.
     */
    'accounts' => [
        'retained_earnings' => '3200',
        'current_year_earnings' => '3900',
        'suspense' => '9500',
        'rounding' => '9510',
        'customer_deposits' => '2350',
        'goods_received_not_invoiced' => '2150',
        'trade_receivables' => '1200',
        'trade_payables' => '2000',
        'inventory' => '1300',
        'allocated_overhead' => '7900',
        'overhead_recharged' => '7910',
    ],

    /*
     * Which account classes must carry a cost centre. Departmental P&L needs both
     * sides of the income statement, so revenue is included; equity never is, and
     * control accounts never are -- a dimension on the AR control account would mean
     * reconciling the subledger per cost centre, which breaks the moment one customer
     * payment settles invoices from two departments.
     */
    'cost_centre_required_classes' => [
        'revenue', 'cost_of_sales', 'expense',
    ],

    'currency' => [
        'default' => 'IQD',
        'decimal_places' => 0,
    ],

    'fiscal' => [
        'periods_per_year' => 12,
        'adjustment_period' => 13,
    ],

    /*
     * Manual entries above this value need a second pair of eyes. Amounts are in the
     * functional currency's minor unit (whole dinars for IQD).
     */
    'approval_thresholds' => [
        'manual_journal' => 50_000_000,
    ],
];
