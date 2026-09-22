<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\AccountType;

/**
 * VR-34: a payment may not exceed what is owed to that counterparty on that
 * account -- contractor and supplier payments (TT-18) and the government share
 * payment (TT-29).
 */
final class Vr34PaymentWithinPayable extends BaseRule
{
    public function __construct(private readonly SubledgerQuery $subledger) {}

    public function code(): string
    {
        return 'VR-34';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-18', 'TT-29')) {
            return [];
        }

        $violations = [];

        foreach ($context->debitLines() as $line) {
            if ($context->account($line)->account_type !== AccountType::Liability || $line->counterparty_id === null) {
                continue;
            }

            $owed = $this->subledger->owedOn($line->account_id, (int) $line->counterparty_id, $context->header->id);

            if ($line->debit > $owed) {
                $violations[] = $this->violation(['outstanding' => $owed], $line);
            }
        }

        return $violations;
    }
}
