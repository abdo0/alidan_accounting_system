<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

/**
 * RPT-07 Profit and loss: gross revenue, government share, net retained revenue,
 * direct operating costs, gross operating result, administrative expenses, net
 * result -- for a range, from report_mappings.
 */
final class Rpt07ProfitAndLoss extends StatementReport
{
    public function code(): string
    {
        return 'RPT-07';
    }

    protected function statement(): string
    {
        return 'PL';
    }

    public function filters(): array
    {
        return ['from', 'to', 'fiscal_year', 'period', 'projects', 'cost_centers'];
    }
}
