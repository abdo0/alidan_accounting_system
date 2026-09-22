<?php

declare(strict_types=1);

namespace App\Domain\Contractors;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Reporting\AccountSets;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * The contractor and supplier subledger (M06, Document B §4.5), a view over
 * journal_lines grouped by counterparty and contract. Which accounts carry
 * certification, invoices, advances, recoveries, retention and payments is read
 * from the posting rules that define those transactions (PR-13 ... PR-18), so the
 * subledger follows the rules, not a list of codes.
 */
final class SubledgerQuery
{
    public function __construct(private readonly AccountSets $sets) {}

    /** @return array<string, list<int>> */
    public function accounts(): array
    {
        return [
            'certified' => $this->sets->ofRule('PR-14', 'credit'),
            'invoiced' => $this->sets->ofRule('PR-18', 'credit'),
            'advances' => $this->sets->ofRule('PR-13', 'debit'),
            'recovered' => $this->sets->ofRule('PR-15', 'credit'),
            'retention' => $this->sets->ofRule('PR-16', 'credit'),
            'paid' => $this->sets->ofRule('PR-17', 'debit'),
        ];
    }

    /**
     * The figures of one counterparty (optionally one contract) up to a date,
     * excluding one entry (the one being validated).
     *
     * @return array{certified: int, invoiced: int, advances: int, recovered: int, retention: int, paid: int}
     */
    public function figures(int $counterpartyId, ?int $contractId = null, ?DateTimeInterface $asAt = null, ?int $excludingJournal = null): array
    {
        $sets = $this->accounts();

        $query = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->where('jl.counterparty_id', $counterpartyId)
            ->when($contractId !== null, fn ($q) => $q->where('jl.contract_id', $contractId))
            ->when($asAt !== null, fn ($q) => $q->where('jh.posting_date', '<=', $asAt->format('Y-m-d')))
            ->when($excludingJournal !== null, fn ($q) => $q->where('jh.id', '<>', $excludingJournal));

        $sum = fn (string $side, array $accounts): int => $accounts === [] ? 0 : (int) (clone $query)->whereIn('jl.account_id', $accounts)->sum('jl.'.$side);

        return [
            'certified' => $sum('credit', $sets['certified']),
            'invoiced' => $sum('credit', $sets['invoiced']),
            'advances' => $sum('debit', $sets['advances']),
            'recovered' => $sum('credit', $sets['recovered']),
            'retention' => $sum('credit', $sets['retention']),
            'paid' => $sum('debit', $sets['paid']),
        ];
    }

    /** Cumulative certified value: credits to the certified-works account. */
    public function certified(int $counterpartyId, ?int $contractId, ?int $excludingJournal = null): int
    {
        return $this->sumOn($this->accounts()['certified'], 'credit', $counterpartyId, $contractId, $excludingJournal);
    }

    /** @param  list<int>  $accounts */
    public function sumOn(array $accounts, string $side, int $counterpartyId, ?int $contractId, ?int $excludingJournal = null): int
    {
        if ($accounts === []) {
            return 0;
        }

        return (int) DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->where('jl.counterparty_id', $counterpartyId)
            ->whereIn('jl.account_id', $accounts)
            ->when($contractId !== null, fn ($q) => $q->where('jl.contract_id', $contractId))
            ->when($excludingJournal !== null, fn ($q) => $q->where('jh.id', '<>', $excludingJournal))
            ->sum('jl.'.$side);
    }

    /** Net credit balance of one counterparty on one account (what is owed to it). */
    public function owedOn(int $accountId, int $counterpartyId, ?int $excludingJournal = null): int
    {
        return $this->sumOn([$accountId], 'credit', $counterpartyId, null, $excludingJournal)
            - $this->sumOn([$accountId], 'debit', $counterpartyId, null, $excludingJournal);
    }
}
