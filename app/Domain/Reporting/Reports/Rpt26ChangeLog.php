<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

/**
 * RPT-26 Change and reclassification log: the accounting-relevant subset of the
 * audit trail -- approved changes to the chart, parameters and mappings,
 * reversals, reclassifications and imported reclassification-log rows.
 */
final class Rpt26ChangeLog extends Rpt32AuditTrail
{
    public function code(): string
    {
        return 'RPT-26';
    }

    public function permission(): string
    {
        return 'reports.view';
    }

    /** @return list<string> */
    protected function actions(): array
    {
        return [
            'change_request_apply', 'param_change', 'high_risk_param_change', 'journal_reverse', 'reclassification_log',
            'mapping_change', 'period_reopen', 'period_admin_unlock', 'document_evidence',
        ];
    }
}
