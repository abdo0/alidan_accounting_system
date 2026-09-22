<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Dashboard;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvancePosition;
use App\Domain\Cash\Reconciliation;
use App\Domain\Cash\ReconciliationService;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\MasterData\BankAccount;
use App\Domain\Reporting\AccountSets;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\LedgerQuery;
use App\Domain\Reporting\StatementEngine;

/**
 * The executive dashboard's figures (RPT-27): each one computed from the ledger
 * when asked, each with the report it drills into.
 */
final class Kpis
{
    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly AccountSets $sets,
        private readonly StatementEngine $statements,
    ) {}

    /** @return list<array{key: string, value: int, report: string, filters: array<string, mixed>, amount: bool}> */
    public function all(FilterSet $filters): array
    {
        $balances = $this->ledger->balancesAsAt($filters);
        $sum = fn (array $accounts): int => array_sum(array_intersect_key($balances, array_flip($accounts)));
        $claims = array_merge([], ...array_values($this->sets->shareholderClaims()));
        $pl = $this->statements->generate('PL', FilterSet::fromArray(['from' => $filters->asAt()->startOfYear()->toDateString(), 'to' => $filters->asAt()->toDateString()]));
        $line = fn (string $code): int => (int) ($pl->firstWhere(fn (array $r): bool => $r['line']->code === $code)['value'] ?? 0);

        $unreconciled = BankAccount::query()->get()->filter(function (BankAccount $account): bool {
            $latest = Reconciliation::query()->where('bank_account_id', $account->id)->orderByDesc('as_at_date')->first();

            return $latest === null ? false : ! app(ReconciliationService::class)->isReconciled($latest);
        })->count();

        $asAt = ['as_at' => $filters->asAt()->toDateString()];

        return [
            ['key' => 'funding', 'value' => -$sum($claims), 'report' => 'RPT-11', 'filters' => [], 'amount' => true],
            ['key' => 'cash', 'value' => $sum($this->sets->cash()), 'report' => 'RPT-10', 'filters' => [], 'amount' => true],
            ['key' => 'cip', 'value' => $sum($this->sets->onLine('SFP-A-040')), 'report' => 'RPT-18', 'filters' => [], 'amount' => true],
            ['key' => 'advances', 'value' => (int) Advance::query()->get()->sum(fn (Advance $a): int => AdvancePosition::of($a, $filters->asAt())->outstanding), 'report' => 'RPT-13', 'filters' => $asAt, 'amount' => true],
            ['key' => 'payables', 'value' => -$sum($this->sets->onLine('SFP-L-110', 'SFP-L-115')), 'report' => 'RPT-16', 'filters' => $asAt, 'amount' => true],
            ['key' => 'government_share', 'value' => -$sum($this->sets->ofRule('PR-25', 'credit')), 'report' => 'RPT-19', 'filters' => [], 'amount' => true],
            ['key' => 'revenue', 'value' => $line('PL-010'), 'report' => 'RPT-07', 'filters' => [], 'amount' => true],
            ['key' => 'result', 'value' => $line('PL-900'), 'report' => 'RPT-07', 'filters' => [], 'amount' => true],
            ['key' => 'missing_documents', 'value' => JournalHeader::query()->whereIn('status', JournalStatus::ledgerValues())->where('doc_status', '!=', DocStatus::Complete)->count(), 'report' => 'RPT-24', 'filters' => [], 'amount' => false],
            ['key' => 'unreconciled_cash', 'value' => $unreconciled, 'report' => 'RPT-10', 'filters' => [], 'amount' => false],
            ['key' => 'open_exceptions', 'value' => ControlException::query()->where('status', '!=', ExceptionStatus::Resolved)->count(), 'report' => 'RPT-24', 'filters' => [], 'amount' => false],
            ['key' => 'ledger_difference', 'value' => PostingService::ledgerDifference(), 'report' => 'RPT-04', 'filters' => [], 'amount' => true],
        ];
    }
}
