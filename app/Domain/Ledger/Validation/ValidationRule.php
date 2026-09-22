<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/**
 * One rule of Document C tab 18. Each class carries its own specification code, so
 * a refusal always names the rule that caused it.
 */
interface ValidationRule
{
    public function code(): string;

    public function group(): RuleGroup;

    public function severity(): Severity;

    public function appliesAt(Checkpoint $checkpoint): bool;

    /** @return list<Violation> */
    public function check(PostingContext $context): array;
}
