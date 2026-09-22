<?php

declare(strict_types=1);

return [
    'not_authorised' => 'Only the Finance Manager may commit the migration.',
    'signoff_required' => 'A committed migration needs the external sign-off reference (--signoff).',
    'unknown_account' => ':where: account :code is not in the approved chart.',
    'fractional' => ':where: the amount is not whole dinars; nothing is rounded.',
    'unbalanced' => ':where: debit and credit amounts differ or are missing.',
    'unknown_project' => ':where: project ":project" is not one of the four approved projects.',
    'cutoff_required' => 'Undated rows need the migration cut-off date parameter set first; no date is invented.',
    'no_period' => 'No accounting period contains :date.',
    'missing_cost_center' => 'Journal :jv carries no cost centre in the source (EXC-SYS-03).',
    'chain_mismatch' => 'Source Exception / Accounting Decision Required — journal :jv is declared as ":step" but its accounts do not fit that step.',
    'unknown_value' => 'Source Exception / Accounting Decision Required — journal :jv carries the unrecognised value ":value".',
    'historic_advance' => 'Historic custody position migrated from the authoritative ledger (MIG-17)',
    'control_unknown' => 'Control not recognised.',
    'per_account_difference' => 'Migrated lines leave a difference of :difference.',
    'exceptions_missing' => ':open of :expected register exceptions are open.',
    'no_run' => 'No migration has been committed yet. Load the authoritative workbook with shh:migrate:file1.',
];
