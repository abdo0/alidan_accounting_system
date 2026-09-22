<?php

declare(strict_types=1);

return [
    'duplicate' => [
        'exact' => 'Same amount, counterparty and period as :jv',
        'near_date' => 'Same amount and counterparty as :jv, dated within the near-date window (or undated)',
        'source_reference' => 'Source reference :ref is already posted on :jv',
        'not_authorised' => 'Dispositioning a duplicate flag needs the Review permission.',
        'note_required' => 'A disposition needs a note.',
    ],
    'exception' => [
        'cash_variance' => 'Cash account :account does not agree to the count or statement of :date',
        'overdue_advance' => 'Advance :ref is past its settlement deadline (:deadline) with a balance outstanding',
        'pending_evidence' => 'Settlement claim on advance :ref awaits evidence',
        'not_authorised' => 'You are not permitted to do this to an exception.',
        'already_resolved' => 'This exception is already resolved.',
        'document' => 'Journal :jv posted with document status :status',
        'suspense' => 'Journal :jv posted to the suspense account :account',
        'amount_difference' => 'Journal :jv line :line carries an amount difference against its source',
        'suspense_cleared' => 'Cleared by :jv (resolution :ref)',
    ],
];
