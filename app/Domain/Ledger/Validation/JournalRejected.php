<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

use DomainException;

/**
 * A workflow step refused by the validation chain. Carries every finding, not just
 * the first: Document B §2.2 -- "any Blocking failure aborts with all messages".
 */
final class JournalRejected extends DomainException
{
    /** @param  list<Violation>  $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode("\n", array_map(fn (Violation $v): string => $v->describe(), $violations)));
    }

    /** @return list<string> */
    public function rules(): array
    {
        return array_values(array_unique(array_map(fn (Violation $v): string => $v->rule, $this->violations)));
    }

    public function has(string $rule): bool
    {
        return in_array($rule, $this->rules(), true);
    }
}
