<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Rules;

/**
 * A parsed account selector: inclusive code ranges plus the aliases that resolve
 * only in context (@any_posting, @original_debit).
 */
final readonly class AccountSet
{
    /**
     * @param  list<array{string, string}>  $ranges
     * @param  list<string>  $aliases
     */
    public function __construct(
        public array $ranges,
        public array $aliases = [],
    ) {}

    /** @param  list<string>  $originalDebitCodes  accounts debited on the linked journal */
    public function contains(string $code, array $originalDebitCodes = []): bool
    {
        if (in_array('@any_posting', $this->aliases, true)) {
            return true;
        }

        if (in_array('@original_debit', $this->aliases, true) && in_array($code, $originalDebitCodes, true)) {
            return true;
        }

        foreach ($this->ranges as [$from, $to]) {
            if ($code >= $from && $code <= $to) {
                return true;
            }
        }

        return false;
    }

    public function needsOriginalDebit(): bool
    {
        return in_array('@original_debit', $this->aliases, true);
    }
}
