<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/** Everything the chain found, in the order it ran. */
final readonly class ValidationResult
{
    /** @param  list<Violation>  $violations */
    public function __construct(public array $violations) {}

    /** @return list<Violation> */
    public function blocking(): array
    {
        return array_values(array_filter($this->violations, fn (Violation $v): bool => $v->isBlocking()));
    }

    /** @return list<Violation> */
    public function warnings(): array
    {
        return array_values(array_filter($this->violations, fn (Violation $v): bool => $v->isWarning()));
    }

    /**
     * @param  list<string>  $acknowledged  rule codes the user has acknowledged
     * @return list<Violation>
     */
    public function unacknowledgedWarnings(array $acknowledged): array
    {
        return array_values(array_filter($this->warnings(), fn (Violation $v): bool => ! in_array($v->rule, $acknowledged, true)));
    }

    public function hasBlocking(): bool
    {
        return $this->blocking() !== [];
    }

    /** @return list<string> */
    public function ruleCodes(): array
    {
        return array_values(array_unique(array_map(fn (Violation $v): string => $v->rule, $this->violations)));
    }
}
