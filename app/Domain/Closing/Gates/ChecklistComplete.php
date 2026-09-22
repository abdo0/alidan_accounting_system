<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Closing\Enums\ChecklistStatus;
use App\Domain\Organisation\AccountingPeriod;

/** Every one of the 25 checklist tasks is done. */
final class ChecklistComplete implements CloseGate
{
    public function blockers(AccountingPeriod $period): array
    {
        $open = ChecklistTask::query()->where('period_id', $period->id)->where('status', ChecklistStatus::Pending)->count();
        $total = ChecklistTask::query()->where('period_id', $period->id)->count();

        return $open === 0 && $total > 0 ? [] : [__('closing.gates.checklist', ['open' => $total === 0 ? '—' : $open])];
    }
}
