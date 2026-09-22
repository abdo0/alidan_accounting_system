<?php

declare(strict_types=1);

return [
    'not_authorised' => 'You are not permitted to change this period.',
    'reason_required' => 'Reopening a period needs a reason.',
    'wrong_status' => 'The period is :status; that step is not available.',
    'gates' => [
        'checklist' => ':open closing task(s) are not complete.',
        'unposted' => ':count journal(s) in the period are not posted.',
        'duplicates' => ':count duplicate flag(s) in the period await a disposition.',
        'exception' => 'Exception :no is open and blocks the close: :subject',
        'custodian' => 'Cash account :account has no custodian or responsible accountant on record.',
        'count' => 'Cash account :account has no approved count or reconciliation in the period.',
    ],
    'actions' => [
        'soft_close' => 'Soft close',
        'final_close' => 'Final close',
        'reopen' => 'Reopen',
        'lock' => 'Lock',
        'complete' => 'Mark complete',
        'blockers' => 'What blocks the close',
    ],
    'done' => 'Period updated.',
    'ready' => 'Nothing blocks the final close.',
];
