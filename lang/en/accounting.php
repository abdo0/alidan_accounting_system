<?php

declare(strict_types=1);

return [
    'currency' => [
        'iqd' => 'IQD',
        'usd' => 'USD',
    ],

    'account_class' => [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'cost_of_sales' => 'Cost of sales',
        'expense' => 'Expense',
        'other_income' => 'Other income',
        'other_expense' => 'Other expense',
        'tax' => 'Tax',
        'clearing' => 'Clearing and suspense',
        'statistical' => 'Statistical',
    ],

    'normal_balance' => [
        'D' => 'Debit',
        'C' => 'Credit',
    ],

    'statement' => [
        'BS' => 'Balance Sheet',
        'PL' => 'Income Statement',
        'SOCE' => 'Statement of Changes in Equity',
        'NONE' => 'Not presented',
    ],

    'journal' => [
        'SDB' => 'Sales Day Book',
        'PDB' => 'Purchases Day Book',
        'RIB' => 'Returns Inwards Day Book',
        'ROB' => 'Returns Outwards Day Book',
        'CB' => 'Cash Book',
        'PCB' => 'Petty Cash Book',
        'GJ' => 'General Journal',
        'PAY' => 'Payroll Journal',
        'FA' => 'Fixed Assets Journal',
        'INV' => 'Inventory Journal',
        'ALC' => 'Allocation Journal',
        'CLO' => 'Closing Journal',
        'OPN' => 'Opening Journal',
    ],

    'entry_status' => [
        'draft' => 'Draft',
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
        'rejected' => 'Rejected',
    ],

    'period_status' => [
        'open' => 'Open',
        'soft_closed' => 'Soft closed',
        'closed' => 'Closed',
        'permanently_closed' => 'Permanently closed',
    ],

    'cost_centre_type' => [
        'operating' => 'Operating',
        'support' => 'Support',
        'project' => 'Project',
        'admin' => 'Administration',
        'statistical' => 'Statistical',
    ],

    'reversal_reason' => [
        'data_entry_error' => 'Data entry error',
        'wrong_period' => 'Wrong period',
        'wrong_account' => 'Wrong account',
        'wrong_amount' => 'Wrong amount',
        'duplicate' => 'Duplicate entry',
        'cancelled_transaction' => 'Cancelled transaction',
        'audit_adjustment' => 'Audit adjustment',
        'dimension_correction' => 'Cost centre correction',
    ],

    'statement' => [
        'for_year_ended' => 'For the financial year ended :date',
        'for_period_ended' => 'For period :period of :year',
        'code_column' => 'Chart code',
        'account_column' => 'Account name',
        'current_year' => 'Current year / IQD',
        'prior_year' => 'Prior year / IQD',
        'statement_ref' => 'Statement no.',
        'continued' => 'Continued /',
        'awaiting_data' => 'This statement is defined but its source module (:module) is not yet built, so it carries no figures.',
    ],

    'validation' => [
        'unbalanced' => 'The entry does not balance: debits are :debit and credits are :credit.',
        'minimum_lines' => 'A journal entry needs at least two lines.',
        'line_both_sides' => 'Line :line carries both a debit and a credit.',
        'line_zero' => 'Line :line has no amount.',
        'period_closed' => 'Period :period is :status and cannot be posted to.',
        'date_outside_period' => 'The entry date :date falls outside period :period.',
        'account_inactive' => 'Account :account is not active.',
        'account_not_postable' => 'Account :account is a heading and cannot be posted to.',
        'control_account' => 'Account :account is a control account; post through its subledger.',
        'cost_centre_required' => 'Account :account requires a cost centre.',
        'cost_centre_inactive' => 'Cost centre :cost_centre is not active on :date.',
        'cross_entity' => 'All lines of an entry must belong to one entity.',
        'adjusting_needs_both' => 'An adjusting entry must touch a balance sheet account and an income statement account.',
        'adjusting_no_cash' => 'An adjusting entry may not involve a cash or bank account.',
        'evidence_required' => 'A manual entry needs a source document reference and at least one attachment.',
        'approval_required' => 'This entry needs approval before it can be posted.',
        'approver_is_creator' => 'An entry cannot be approved by the person who created it.',
        'memo_mixed_with_financial' => 'A contra (memorandum) entry may not also touch financial accounts.',
        'memo_no_partner' => 'Account :account is a contra account with no paired account recorded.',
        'memo_unpaired' => 'Account :account must be mirrored by :partner on the opposite side for the same amount.',
        'already_posted' => 'This entry has already been posted.',
        'currency_not_iqd' => 'This installation posts in IQD only.',
    ],
];
