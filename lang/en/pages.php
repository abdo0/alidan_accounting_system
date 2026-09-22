<?php

declare(strict_types=1);

return [
    'user' => [
        'identity' => 'Identity',
        'access' => 'Access',
        'password_help' => 'Leave blank to keep the current password.',
        'service_account_help' => 'A service account drives integrations. It never reaches the panel.',
    ],
    'change_request' => [
        'proposed' => 'Change proposed. It takes effect once someone else approves it.',
        'decided' => 'Decision recorded.',
        'system' => 'System (approved specification)',
    ],
    'parameter' => [
        'not_set' => 'Not set',
        'in_force' => 'In force',
        'changed' => 'New value recorded. The previous value remains in the history.',
        'board_reference_required' => 'A high-risk parameter: give the board decision reference.',
    ],
    'funding' => [
        'actual_payment_source_help' => 'Audit only. Never used in a posting rule (Policy 8).',
    ],
    'journal' => [
        'header' => 'Entry',
        'lines' => 'Lines',
        'controls' => 'Evidence & control',
        'workflow' => 'Workflow',
        'totals' => 'Totals',
        'read_only' => 'A posted entry is never edited or deleted. Correct it by reversal or reclassification.',
        'saved' => 'Draft saved.',
        'submitted' => 'Submitted for review.',
        'reviewed' => 'Reviewed.',
        'approved' => 'Approved.',
        'rejected' => 'Returned to draft.',
        'posted' => 'Posted as :jv.',
        'reversed' => 'Reversed by :jv.',
        'refused' => 'The entry was refused',
        'acknowledge' => 'I acknowledge these warnings',
        'simple' => 'Simple entry',
        'simple_help' => 'One debit account and one credit account for one amount.',
        'amount' => 'Amount',
        'debit_account' => 'Debit account',
        'credit_account' => 'Credit account',
        'balanced' => 'Balanced',
        'not_balanced' => 'Not balanced',
        'documents' => 'Documents',
        'approvals' => 'Approval history',
        'upload' => 'Attach document',
    ],
    'exception' => [
        'resolved' => 'Exception resolved.',
    ],
    'duplicate' => [
        'undecided' => 'Awaiting disposition',
    ],
    'government_share' => [
        'title' => 'Government share',
        'prepare' => 'Prepare recognition entry',
    ],
];
