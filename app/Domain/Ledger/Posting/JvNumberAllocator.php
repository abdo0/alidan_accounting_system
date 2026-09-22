<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\JournalHeader;

/**
 * VR-10: the journal number is system-generated, unique per company and fiscal
 * year, and gapless. It is drawn inside the posting transaction as the last
 * fallible step, so a refusal never burns a number.
 *
 * New entries read JV-2026-00001. Migrated entries keep their source number
 * (JV-0001 ... JV-1268), so the two series can never collide.
 */
final class JvNumberAllocator
{
    public function __construct(private readonly Sequencer $sequencer) {}

    public function next(JournalHeader $header): string
    {
        $year = $header->period->fiscalYear->year_code;

        return $this->sequencer->next('jv', "company:{$header->company_id}|fy:{$year}", "JV-{$year}-", 5);
    }
}
