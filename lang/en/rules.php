<?php

declare(strict_types=1);

return [
    'parameters' => [
        'not_authorised' => 'Only the Finance Manager may change a parameter.',
        'reason_required' => 'A parameter change needs a reason and an approval reference.',
        'not_after_current' => 'A new value must take effect after the value in force (from :from).',
        'bad_value' => 'The value is not a valid :type.',
    ],
    'change_requests' => [
        'not_authorised' => 'You are not permitted to change this record.',
        'reason_required' => 'A reason is required.',
        'field_not_changeable' => 'These fields cannot be changed: :fields.',
        'not_open' => 'This change request has already been decided.',
        'maker_checker' => 'A change must be approved by someone other than the person who proposed it.',
        'balance_not_nil' => 'An account may be deactivated only when its balance is nil.',
    ],
    'journal' => [
        'not_authorised' => 'You are not permitted to do this to this entry.',
        'wrong_status' => 'This entry is :status; that step is not available.',
        'rejection_comment' => 'A rejection needs a comment.',
        'reversal_description' => 'Reversal of :jv',
        'reclassification_description' => 'Reclassification of :jv',
    ],
    'documents' => [
        'status_only_improves' => 'Document status may only improve: Missing, then Partial, then Complete.',
        'evidence_recorded' => 'Supporting document recorded',
        'received' => 'Document received (:ref)',
        'type_not_allowed' => 'That file type is not accepted. Allowed: :types.',
        'too_large' => 'The file is larger than the permitted size.',
        'bad_type' => 'Unknown document type.',
        'infected' => 'The file failed the virus scan and was not stored.',
    ],
    'advances' => [
        'claim_amount' => 'A claim needs a positive amount.',
    ],
    'cash' => [
        'not_authorised' => 'You are not permitted to do this reconciliation step.',
        'not_submitted' => 'Only a submitted reconciliation can be approved.',
    ],
    'revenue' => [
        'nothing_to_recognise' => 'There is no government share left to recognise for this period.',
        'recognition_description' => 'Government revenue share for :period at rate :rate',
    ],
];
