<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Ledger\Account;
use App\Domain\Ledger\Journal;
use App\Domain\Organisation\FiscalPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rules V-01..V-18 from docs/04 §4.2. Every rule is checked before anything is
 * written; failing any one aborts the whole post. There is no partial state.
 *
 * Runs before the sequence lock is taken, so the per-journal serialisation lock is
 * not held across account and cost-centre lookups -- otherwise month-end batch
 * posting becomes a queue.
 */
final class PostingValidator
{
    /** @var list<array{rule: string, message: string}> */
    private array $violations = [];

    public function assertValid(JournalEntryDraft $draft, FiscalPeriod $period, ?User $actor = null): void
    {
        $this->violations = [];

        $this->checkStructure($draft);
        $this->checkPeriod($draft, $period);
        $this->checkCurrency($draft);

        $accounts = $this->loadAccounts($draft);
        $this->checkAccounts($draft, $accounts);
        $this->checkDimensions($draft, $accounts);
        $this->checkAdjusting($draft, $accounts);
        $this->checkMemorandum($draft, $accounts);
        $this->checkJournal($draft);
        $this->checkEvidence($draft);

        if ($this->violations !== []) {
            throw new PostingException($this->violations);
        }
    }

    /** @param  array<string, string>  $replace */
    private function fail(string $rule, string $key, array $replace = []): void
    {
        $this->violations[] = ['rule' => $rule, 'message' => __($key, $replace)];
    }

    /** V-01, V-02 */
    private function checkStructure(JournalEntryDraft $draft): void
    {
        if (count($draft->lines) < 2) {
            $this->fail('V-02', 'accounting.validation.minimum_lines');
        }

        foreach ($draft->lines as $index => $line) {
            $lineNo = $index + 1;

            if ($line->isDebit() && $line->isCredit()) {
                $this->fail('V-02', 'accounting.validation.line_both_sides', ['line' => (string) $lineNo]);
            }

            if (! $line->isDebit() && ! $line->isCredit()) {
                $this->fail('V-02', 'accounting.validation.line_zero', ['line' => (string) $lineNo]);
            }
        }

        if (! $draft->isBalanced()) {
            $this->fail('V-01', 'accounting.validation.unbalanced', [
                'debit' => $draft->totalDebit(),
                'credit' => $draft->totalCredit(),
            ]);
        }
    }

    /** V-03, V-04, V-09 */
    private function checkPeriod(JournalEntryDraft $draft, FiscalPeriod $period): void
    {
        if (! $period->acceptsPostings()) {
            $this->fail('V-03', 'accounting.validation.period_closed', [
                'period' => $period->name,
                'status' => __('accounting.period_status.'.$period->status),
            ]);
        }

        if (! $period->contains($draft->entryDate)) {
            $this->fail('V-04', 'accounting.validation.date_outside_period', [
                'date' => $draft->entryDate->toDateString(),
                'period' => $period->name,
            ]);
        }

        if ($period->entity_id !== $draft->entityId) {
            $this->fail('V-09', 'accounting.validation.cross_entity');
        }
    }

    /**
     * V-10, V-11. Under IQD-only these reduce to an assertion rather than an FX
     * calculation, but they are kept: the functional columns are still written, and a
     * report reading them must never silently return zero.
     */
    private function checkCurrency(JournalEntryDraft $draft): void
    {
        if ($draft->currencyCode !== 'IQD') {
            $this->fail('V-10', 'accounting.validation.currency_not_iqd');
        }
    }

    /** @return Collection<int, Account> */
    private function loadAccounts(JournalEntryDraft $draft): Collection
    {
        $ids = array_map(fn (JournalLineDraft $line): int => $line->accountId, $draft->lines);

        return Account::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');
    }

    /**
     * V-05, V-06
     *
     * @param  Collection<int, Account>  $accounts
     */
    private function checkAccounts(JournalEntryDraft $draft, Collection $accounts): void
    {
        foreach ($draft->lines as $line) {
            $account = $accounts->get($line->accountId);

            if (! $account instanceof Account) {
                $this->fail('V-05', 'accounting.validation.account_inactive', ['account' => (string) $line->accountId]);

                continue;
            }

            if (! $account->is_active) {
                $this->fail('V-05', 'accounting.validation.account_inactive', ['account' => $account->label()]);
            }

            if (! $account->is_postable) {
                $this->fail('V-05', 'accounting.validation.account_not_postable', ['account' => $account->label()]);
            }

            // A control account is written by its subledger only; a manual entry into
            // it is how a subledger stops agreeing with its control account.
            if ($account->is_control_account && $draft->sourceType === 'manual') {
                $this->fail('V-06', 'accounting.validation.control_account', ['account' => $account->label()]);
            }
        }
    }

    /**
     * V-07, V-08
     *
     * @param  Collection<int, Account>  $accounts
     */
    private function checkDimensions(JournalEntryDraft $draft, Collection $accounts): void
    {
        $costCentreIds = array_filter(array_map(
            fn (JournalLineDraft $line): ?int => $line->costCentreId,
            $draft->lines
        ));

        $costCentres = $costCentreIds === []
            ? collect()
            : CostCentre::query()->whereIn('id', array_unique($costCentreIds))->get()->keyBy('id');

        foreach ($draft->lines as $line) {
            $account = $accounts->get($line->accountId);

            if (! $account instanceof Account) {
                continue;
            }

            if ($line->costCentreId === null) {
                // The standard never allocates element 35 (مشتريات البضائع والأراضي بغرض
                // البيع) to a cost centre -- it goes straight to the trading account and
                // is added back when reconciling total uses. A blanket "every use needs a
                // centre" rule would reject conformant behaviour.
                if ($this->isCostCentreExempt($account)) {
                    continue;
                }

                if ($account->requiresCostCentreOn($draft->entryDate)) {
                    $this->fail('V-07', 'accounting.validation.cost_centre_required', [
                        'account' => $account->label(),
                    ]);
                }

                continue;
            }

            $centre = $costCentres->get($line->costCentreId);

            if (! $centre instanceof CostCentre
                || ! $centre->is_postable
                || ! $centre->isActiveOn($draft->entryDate)) {
                $this->fail('V-08', 'accounting.validation.cost_centre_inactive', [
                    'cost_centre' => $centre?->label() ?? (string) $line->costCentreId,
                    'date' => $draft->entryDate->toDateString(),
                ]);
            }
        }
    }

    /**
     * V-12. Doc A is explicit on both halves: an adjusting entry touches at least one
     * balance sheet and one income statement account, and never the cash account.
     *
     * @param  Collection<int, Account>  $accounts
     */
    private function checkAdjusting(JournalEntryDraft $draft, Collection $accounts): void
    {
        if (! $draft->isAdjusting) {
            return;
        }

        $touched = $accounts->only(array_map(
            fn (JournalLineDraft $line): int => $line->accountId,
            $draft->lines
        ));

        $hasBalanceSheet = $touched->contains(fn (Account $a): bool => $a->isBalanceSheet());
        $hasProfitAndLoss = $touched->contains(fn (Account $a): bool => $a->isProfitAndLoss());

        if (! $hasBalanceSheet || ! $hasProfitAndLoss) {
            $this->fail('V-12', 'accounting.validation.adjusting_needs_both');
        }

        if ($touched->contains(fn (Account $a): bool => $a->isCashOrBank())) {
            $this->fail('V-12', 'accounting.validation.adjusting_no_cash');
        }
    }

    private function isCostCentreExempt(Account $account): bool
    {
        /** @var list<string> $prefixes */
        $prefixes = config('accounting.cost_centre_exempt_prefixes', []);

        foreach ($prefixes as $prefix) {
            if (str_starts_with($account->code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * V-19 — الحسابات المتقابلة.
     *
     * Classes 19 and 29 are a paired memorandum mechanism: "كل ما يقيد في أحد الحسابين
     * المتقابلين يقيد في الجانب المعاكس من الحساب الآخر". Every entry to one leg is
     * mirrored on the opposite side of its partner, so the pair nets to nothing and
     * neither appears within the balance sheet totals.
     *
     * Two rules follow. A memorandum entry may not mix with financial accounts -- that
     * would put a real amount against a memo one. And each leg must have its partner in
     * the same entry, on the opposite side, for the same amount.
     *
     * Partners pair by TRAILING digits, never by the word مقابل: on 1922 and 1924 the
     * prefix sits on the opposite side from where the pattern suggests.
     *
     * @param  Collection<int, Account>  $accounts
     */
    private function checkMemorandum(JournalEntryDraft $draft, Collection $accounts): void
    {
        $memoLines = [];
        $financialLines = 0;

        foreach ($draft->lines as $line) {
            $account = $accounts->get($line->accountId);

            if (! $account instanceof Account) {
                continue;
            }

            if ($account->isMemorandum()) {
                $memoLines[] = [$line, $account];
            } else {
                $financialLines++;
            }
        }

        if ($memoLines === []) {
            return;
        }

        if ($financialLines > 0) {
            $this->fail('V-19', 'accounting.validation.memo_mixed_with_financial');

            return;
        }

        // Index what the entry offers on each side, so each leg can find its partner.
        $offered = [];

        foreach ($memoLines as [$line, $account]) {
            $side = $line->isDebit() ? 'D' : 'C';
            $amount = $line->isDebit() ? $line->debit : $line->credit;
            $offered[$account->code][$side][] = $amount;
        }

        foreach ($memoLines as [$line, $account]) {
            $partner = $account->contra_pair_code;

            if ($partner === null) {
                $this->fail('V-19', 'accounting.validation.memo_no_partner', [
                    'account' => $account->label(),
                ]);

                continue;
            }

            $oppositeSide = $line->isDebit() ? 'C' : 'D';
            $amount = $line->isDebit() ? $line->debit : $line->credit;
            $available = $offered[$partner][$oppositeSide] ?? [];

            $matched = false;

            foreach ($available as $index => $candidate) {
                if (bccomp($candidate, $amount, 4) === 0) {
                    unset($offered[$partner][$oppositeSide][$index]);
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $this->fail('V-19', 'accounting.validation.memo_unpaired', [
                    'account' => $account->label(),
                    'partner' => $partner,
                ]);
            }
        }
    }

    /** V-15 */
    private function checkJournal(JournalEntryDraft $draft): void
    {
        $journal = Journal::query()->where('code', $draft->journalCode)->first();

        if (! $journal instanceof Journal || ! $journal->is_active) {
            $this->fail('V-15', 'accounting.validation.account_inactive', ['account' => $draft->journalCode]);

            return;
        }

        if ($draft->sourceType === 'manual' && ! $journal->allows_manual_entry) {
            $this->fail('V-15', 'accounting.validation.control_account', ['account' => $journal->code]);
        }
    }

    /** V-13. Evidence is a posting precondition, not an optional attachment. */
    private function checkEvidence(JournalEntryDraft $draft): void
    {
        if ($draft->sourceType !== 'manual' || $draft->isSystemGenerated) {
            return;
        }

        if (blank($draft->sourceDocumentNo)) {
            $this->fail('V-13', 'accounting.validation.evidence_required');
        }
    }
}
