<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/** One rule's finding against an entry. */
final readonly class Violation
{
    /** @param  array<string, scalar|null>  $params */
    public function __construct(
        public string $rule,
        public Severity $severity,
        public string $message,
        public ?int $lineNo = null,
        public array $params = [],
    ) {}

    public function isBlocking(): bool
    {
        return $this->severity === Severity::Blocking;
    }

    public function isWarning(): bool
    {
        return $this->severity === Severity::Warning;
    }

    public function describe(): string
    {
        $where = $this->lineNo === null ? '' : ' ['.__('fields.line_no').' '.$this->lineNo.']';

        return $this->rule.$where.': '.$this->message;
    }
}
