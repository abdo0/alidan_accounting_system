<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Organisation\AccountingPeriod;

/**
 * No open exception marked as blocking the close of this fiscal year -- EXC-SYS-04
 * holds the 2026 periods open until the source is confirmed complete.
 */
final class NoBlockingException implements CloseGate
{
    public function blockers(AccountingPeriod $period): array
    {
        return ControlException::query()
            ->where('blocks_final_close', true)
            ->where('fiscal_year_id', $period->fiscal_year_id)
            ->where('status', '!=', ExceptionStatus::Resolved)
            ->get()
            ->map(fn (ControlException $e): string => __('closing.gates.exception', ['no' => $e->exception_no, 'subject' => $e->subject]))
            ->all();
    }
}
