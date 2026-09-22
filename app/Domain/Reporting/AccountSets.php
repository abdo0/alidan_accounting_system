<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Ledger\PostingRule;
use App\Domain\Ledger\Rules\SelectorParser;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Shareholder;

/**
 * The account sets the subledger reports need, each read from the chart's own
 * attributes, the shareholder register, or a posting rule's selector -- never from
 * a list of codes in report code (VR-55).
 */
final class AccountSets
{
    public function __construct(private readonly SelectorParser $parser) {}

    /** @return list<int> */
    public function cash(): array
    {
        return $this->where(fn ($q) => $q->where('is_cash_account', true));
    }

    /** @return list<int> accounts that hold advances (VR-07) */
    public function advances(): array
    {
        return $this->where(fn ($q) => $q->where('is_advance_account', true));
    }

    /** @return list<int> contractor and supplier control accounts (RPT-17) */
    public function contractorControl(): array
    {
        return $this->where(fn ($q) => $q->where('subledger', 'contractor'));
    }

    /** @return list<int> accounts whose chart line is the given statement line */
    public function onLine(string ...$fsLines): array
    {
        return $this->where(fn ($q) => $q->whereIn('fs_line_code', $fsLines));
    }

    /**
     * Every account carrying a shareholder's claim: loan, current and capital
     * accounts from the shareholder register, plus capital pending registration.
     *
     * @return array<int, list<int>> shareholder counterparty id => account ids
     */
    public function shareholderClaims(): array
    {
        $claims = [];

        foreach (Shareholder::query()->get() as $shareholder) {
            $claims[$shareholder->counterparty_id] = array_values(array_filter([
                $shareholder->loan_account_id, $shareholder->current_account_id, $shareholder->capital_account_id,
            ]));
        }

        return $claims;
    }

    /** @return list<int> the accounts a posting rule admits on one side */
    public function ofRule(string $ruleCode, string $side): array
    {
        $rule = PostingRule::query()->where('code', $ruleCode)->first();

        if ($rule === null) {
            return [];
        }

        $set = $this->parser->parse($side === 'debit' ? $rule->debit_selector : $rule->credit_selector);

        return Account::query()->postable()->get()
            ->filter(fn (Account $a): bool => $set->contains($a->code))
            ->pluck('id')->values()->all();
    }

    /** @return list<int> */
    private function where(callable $scope): array
    {
        return Account::query()->where('is_group', false)->tap($scope)->pluck('id')->all();
    }
}
