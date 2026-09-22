<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

/**
 * A business rule refused an action. $rule is the specification's own identifier
 * (VR-39, VR-59 ...) so the refusal can be traced to the rule that caused it.
 */
class RuleViolation extends DomainException
{
    /** @param  array<string, scalar|null>  $context */
    public function __construct(
        public readonly string $rule,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** @param  array<string, scalar|null>  $context */
    public static function because(string $rule, string $messageKey, array $context = []): self
    {
        return new self($rule, __($messageKey, $context), $context);
    }
}
