<?php

declare(strict_types=1);

namespace App\Domain\Controls\Duplicates;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterResolver;
use Illuminate\Support\Collection;

/**
 * The three duplicate tests of Document B §2.5, run against posted entries:
 *
 *   1  exact value      same amount, same counterparty, same accounting period
 *   2  source reference the same source reference already posted
 *   3  near date        same amount, same counterparty, dates within ±N days,
 *                       or one of the two dates absent
 *
 * Tests 1 and 3 compare entries of the same transaction type: an advance and the
 * settlement that clears it share amount, holder and period by design, and are not
 * duplicates of each other. Test 2 compares every posted entry -- a source
 * reference is used once, whatever the type.
 *
 * Each match writes a flag -- once -- with its reason and the value at risk. Flags
 * are never removed; posting waits for a disposition (VR-30).
 */
final class DuplicateDetector
{
    private const SCORES = [
        'exact' => 90,
        'source_reference' => 100,
        'near_date' => 70,
    ];

    public function __construct(private readonly ParameterResolver $parameters) {}

    /** @return Collection<int, DuplicateFlag> the flags this entry now carries */
    public function scan(JournalHeader $journal): Collection
    {
        if ($journal->is_migration || $journal->transactionType->is_system) {
            return $journal->exists ? $this->flagsOf($journal) : collect();
        }

        $journal->loadMissing('lines');
        $amount = $journal->totalDebit();
        $counterparties = $journal->lines->pluck('counterparty_id')->filter()->unique()->values()->all();

        foreach ($this->candidates($journal, $amount, $counterparties) as $match) {
            if ($match->period_id === $journal->period_id) {
                $this->flag($journal, $match, DuplicateFlagType::Exact, __('controls.duplicate.exact', ['jv' => $match->jv_no]), $amount);
            }

            if ($this->datesNear($journal, $match)) {
                $this->flag($journal, $match, DuplicateFlagType::NearDate, __('controls.duplicate.near_date', ['jv' => $match->jv_no]), $amount);
            }
        }

        if (trim((string) $journal->source_reference) !== '') {
            $sameReference = JournalHeader::query()
                ->whereKeyNot($journal->id)
                ->whereIn('status', JournalStatus::ledgerValues())
                ->where('source_reference', $journal->source_reference)
                ->whereNull('reversal_of_journal_id')
                ->get();

            foreach ($sameReference as $match) {
                $this->flag($journal, $match, DuplicateFlagType::SourceReference, __('controls.duplicate.source_reference', ['ref' => $journal->source_reference, 'jv' => $match->jv_no]), $amount);
            }
        }

        return $this->flagsOf($journal);
    }

    /**
     * Posted entries with the same amount and a shared counterparty.
     *
     * @param  list<int>  $counterparties
     * @return Collection<int, JournalHeader>
     */
    private function candidates(JournalHeader $journal, int $amount, array $counterparties): Collection
    {
        if ($counterparties === [] || $amount === 0) {
            return collect();
        }

        return JournalHeader::query()
            ->whereKeyNot($journal->id)
            ->where('transaction_type_id', $journal->transaction_type_id)
            ->whereIn('status', JournalStatus::ledgerValues())
            ->whereNull('reversal_of_journal_id')
            ->whereHas('lines', fn ($q) => $q->whereIn('counterparty_id', $counterparties))
            ->with('lines')
            ->get()
            ->filter(fn (JournalHeader $candidate): bool => $candidate->totalDebit() === $amount)
            ->values();
    }

    private function datesNear(JournalHeader $journal, JournalHeader $match): bool
    {
        if ($journal->txn_date === null || $match->txn_date === null) {
            return true;
        }

        $window = $this->parameters->integer(ParameterCode::DuplicateNearDateDays) ?? 7;

        return abs($journal->txn_date->diffInDays($match->txn_date)) <= $window;
    }

    private function flag(JournalHeader $journal, JournalHeader $match, DuplicateFlagType $type, string $reason, int $amount): void
    {
        $exists = DuplicateFlag::query()
            ->where('journal_header_id', $journal->id)
            ->where('matched_journal_id', $match->id)
            ->where('flag_type', $type)
            ->exists();

        if ($exists) {
            return;
        }

        DuplicateFlag::query()->create([
            'journal_header_id' => $journal->id,
            'matched_journal_id' => $match->id,
            'flag_type' => $type,
            'match_reason' => $reason,
            'score' => self::SCORES[$type->value],
            'value_at_risk' => $amount,
            'origin' => 'service',
        ]);
    }

    /** @return Collection<int, DuplicateFlag> */
    private function flagsOf(JournalHeader $journal): Collection
    {
        return DuplicateFlag::query()->where('journal_header_id', $journal->id)->orderBy('id')->get();
    }
}
