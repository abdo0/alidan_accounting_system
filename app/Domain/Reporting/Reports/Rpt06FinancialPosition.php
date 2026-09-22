<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

/** RPT-06 Statement of Financial Position, as at a date, from report_mappings. */
final class Rpt06FinancialPosition extends StatementReport
{
    public function code(): string
    {
        return 'RPT-06';
    }

    protected function statement(): string
    {
        return 'SFP';
    }

    public function filters(): array
    {
        return ['as_at', 'projects'];
    }
}
