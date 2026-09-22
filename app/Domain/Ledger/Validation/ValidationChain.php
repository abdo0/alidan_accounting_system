<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

use App\Domain\Ledger\JournalHeader;
use App\Models\User;

/**
 * Runs the rules of Document B §2.3 in their documented order and returns every
 * finding. It never stops at the first failure: the user sees all of them at once.
 */
final class ValidationChain
{
    /** @var list<ValidationRule> */
    private array $rules;

    /** @param  iterable<ValidationRule>  $rules */
    public function __construct(iterable $rules)
    {
        $rules = is_array($rules) ? $rules : iterator_to_array($rules, false);
        usort($rules, fn (ValidationRule $a, ValidationRule $b): int => [$a->group()->value, $a->code()] <=> [$b->group()->value, $b->code()]);
        $this->rules = $rules;
    }

    public function run(JournalHeader $header, User $actor, Checkpoint $checkpoint): ValidationResult
    {
        return $this->runContext(new PostingContext($header, $actor, $checkpoint));
    }

    public function runContext(PostingContext $context): ValidationResult
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            if (! $rule->appliesAt($context->checkpoint)) {
                continue;
            }

            foreach ($rule->check($context) as $violation) {
                $violations[] = $violation;
            }
        }

        return new ValidationResult($violations);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(fn (ValidationRule $rule): string => $rule->code(), $this->rules);
    }
}
