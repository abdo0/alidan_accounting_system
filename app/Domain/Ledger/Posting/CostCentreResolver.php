<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Account;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the cost centre so users rarely have to choose one. First non-null wins:
 *
 *   1. explicit value on the line
 *   2. document header value
 *   3. the source master record (via a registered CostCentreSourceResolver)
 *   4. the account's default
 *   5. the entering user's default
 *
 * Order matters against validation: resolve first, then validate. Validating first
 * would reject lines that would have defaulted perfectly well.
 */
final class CostCentreResolver
{
    /** @param  iterable<CostCentreSourceResolver>  $sourceResolvers */
    public function __construct(private readonly iterable $sourceResolvers = []) {}

    public function apply(JournalEntryDraft $draft, ?User $actor = null): JournalEntryDraft
    {
        $accounts = $this->loadAccounts($draft);

        $lines = array_map(
            fn (JournalLineDraft $line): JournalLineDraft => $line->withCostCentre(
                $this->resolve($draft, $line, $accounts, $actor)
            ),
            $draft->lines
        );

        return $draft->withLines($lines);
    }

    /** @param  Collection<int, Account>  $accounts */
    private function resolve(
        JournalEntryDraft $draft,
        JournalLineDraft $line,
        Collection $accounts,
        ?User $actor,
    ): ?int {
        if ($line->costCentreId !== null) {
            return $line->costCentreId;
        }

        if ($draft->costCentreId !== null) {
            return $draft->costCentreId;
        }

        foreach ($this->sourceResolvers as $resolver) {
            if ($resolver->supports($draft->sourceType)) {
                $resolved = $resolver->resolve($draft, $line);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        $account = $accounts->get($line->accountId);

        if ($account instanceof Account && $account->default_cost_centre_id !== null) {
            return $account->default_cost_centre_id;
        }

        // Only offer the user's default where the account actually wants a centre;
        // otherwise a balance sheet line picks one up for no reason.
        if ($account instanceof Account
            && $account->requiresCostCentreOn($draft->entryDate)
            && $actor?->default_cost_centre_id !== null) {
            return $actor->default_cost_centre_id;
        }

        return null;
    }

    /** @return Collection<int, Account> */
    private function loadAccounts(JournalEntryDraft $draft): Collection
    {
        $ids = array_map(fn (JournalLineDraft $line): int => $line->accountId, $draft->lines);

        return Account::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');
    }
}
