<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-32: cumulative advance recovery may not exceed the advance issued to that counterparty on that contract. */
final class Vr32RecoveryWithinAdvance extends BaseRule
{
    public function __construct(private readonly SubledgerQuery $subledger) {}

    public function code(): string
    {
        return 'VR-32';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-16')) {
            return [];
        }

        $accounts = $this->subledger->accounts();
        $violations = [];

        foreach ($context->creditLines()->filter(fn ($l): bool => in_array($l->account_id, $accounts['recovered'], true) && $l->counterparty_id !== null) as $line) {
            $issued = $this->subledger->sumOn($accounts['advances'], 'debit', (int) $line->counterparty_id, $line->contract_id, $context->header->id);
            $recovered = $this->subledger->sumOn($accounts['recovered'], 'credit', (int) $line->counterparty_id, $line->contract_id, $context->header->id) + $line->credit;

            if ($recovered > $issued) {
                $violations[] = $this->violation(['limit' => $issued], $line);
            }
        }

        return $violations;
    }
}
