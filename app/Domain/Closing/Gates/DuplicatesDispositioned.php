<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Organisation\AccountingPeriod;

/** No duplicate flag in the period is still waiting for a disposition. */
final class DuplicatesDispositioned implements CloseGate
{
    public function blockers(AccountingPeriod $period): array
    {
        $count = DuplicateFlag::query()
            ->whereNull('disposition')
            ->whereHas('journal', fn ($q) => $q->where('period_id', $period->id))
            ->count();

        return $count === 0 ? [] : [__('closing.gates.duplicates', ['count' => $count])];
    }
}
