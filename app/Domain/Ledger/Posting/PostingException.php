<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use RuntimeException;

/**
 * Carries every rule that failed rather than only the first, so a user fixing a
 * journal entry is not led through one error at a time.
 */
class PostingException extends RuntimeException
{
    /** @param  list<array{rule: string, message: string}>  $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            'The entry cannot be posted: '.implode(' ', array_column($violations, 'message'))
        );
    }

    /** @return list<string> */
    public function messages(): array
    {
        return array_column($this->violations, 'message');
    }

    /** @return list<string> */
    public function rules(): array
    {
        return array_column($this->violations, 'rule');
    }

    public function failed(string $rule): bool
    {
        return in_array($rule, $this->rules(), true);
    }
}
