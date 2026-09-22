<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Ledger\Enums\JournalStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * An advance's position, derived from the ledger (Document B §4.3):
 *
 *   outstanding = amount issued
 *               − settlements (CAPEX + OPEX + fixed asset)
 *               − refunds − reclassifications to personal receivable
 *               − sub-advances passed on
 *
 * computed as Σ(debit − credit) over the issue line and every line linked to the
 * advance. A claim awaiting evidence has no line, so it leaves the amount in the
 * advance. Nothing here is stored.
 */
final class AdvancePosition
{
    /** @param  array<string, int>  $byType */
    private function __construct(
        public readonly Advance $advance,
        public readonly int $issued,
        public readonly array $byType,
        public readonly int $outstanding,
        public readonly int $pendingClaims,
        public readonly CarbonImmutable $asAt,
    ) {}

    public static function of(Advance $advance, ?DateTimeInterface $asAt = null): self
    {
        $asAt = CarbonImmutable::instance($asAt ?? now())->startOfDay();
        $day = $asAt->toDateString();

        $issued = $advance->issue_line_id === null ? 0 : (int) DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('jl.id', $advance->issue_line_id)
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->where('jh.posting_date', '<=', $day)
            ->value('jl.debit');

        $movements = DB::table('advance_settlements as s')
            ->join('journal_lines as jl', 'jl.id', '=', 's.journal_line_id')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->where('s.advance_id', $advance->id)
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->where('jh.posting_date', '<=', $day)
            ->groupBy('s.settlement_type')
            ->selectRaw('s.settlement_type, sum(jl.credit) - sum(jl.debit) AS amount')
            ->pluck('amount', 'settlement_type')
            ->map(fn ($amount): int => (int) $amount)
            ->all();

        $pending = (int) AdvanceSettlement::query()
            ->where('advance_id', $advance->id)
            ->whereNull('journal_line_id')
            ->sum('claimed_amount');

        return new self($advance, $issued, $movements, $issued - array_sum($movements), $pending, $asAt);
    }

    public function settled(SettlementType ...$types): int
    {
        return array_sum(array_map(fn (SettlementType $type): int => $this->byType[$type->value] ?? 0, $types));
    }

    public function status(): AdvanceStatus
    {
        if ($this->outstanding === 0) {
            return AdvanceStatus::Settled;
        }

        $deadline = $this->advance->settlement_deadline;

        return $deadline !== null && $deadline->lessThan($this->asAt) ? AdvanceStatus::Overdue : AdvanceStatus::Open;
    }

    public function ageInDays(): int
    {
        return (int) $this->advance->issue_date->diffInDays($this->asAt);
    }
}
