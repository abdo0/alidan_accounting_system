<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Organisation\AccountingPeriod;

/** No journal in the period is in any state but Posted or Reversed. */
final class NothingUnposted implements CloseGate
{
    public function blockers(AccountingPeriod $period): array
    {
        $count = JournalHeader::query()->where('period_id', $period->id)->whereNotIn('status', JournalStatus::ledgerValues())->count();

        return $count === 0 ? [] : [__('closing.gates.unposted', ['count' => $count])];
    }
}
