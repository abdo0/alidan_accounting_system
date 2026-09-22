<?php

declare(strict_types=1);

namespace App\Domain\Closing\Gates;

use App\Domain\Organisation\AccountingPeriod;

/**
 * A condition final close waits for (Document B §4.11). Each gate returns the
 * reasons the period cannot close yet; none means it may.
 */
interface CloseGate
{
    /** @return list<string> */
    public function blockers(AccountingPeriod $period): array;
}
