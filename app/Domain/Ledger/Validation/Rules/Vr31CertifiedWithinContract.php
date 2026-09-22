<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Contract;

/**
 * VR-31: cumulative certified value may not exceed the contract value plus approved
 * variations. A contract whose value is still undefined cannot be certified
 * against: there is nothing to prove the certificate within.
 */
final class Vr31CertifiedWithinContract extends BaseRule
{
    public function __construct(private readonly SubledgerQuery $subledger) {}

    public function code(): string
    {
        return 'VR-31';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-15')) {
            return [];
        }

        $certifiedAccounts = $this->subledger->accounts()['certified'];
        $violations = [];

        foreach ($context->creditLines()->filter(fn ($l): bool => in_array($l->account_id, $certifiedAccounts, true) && $l->contract_id !== null && $l->counterparty_id !== null) as $line) {
            $contract = Contract::query()->with('amendments')->find($line->contract_id);
            $limit = $contract?->contract_value === null ? null : $contract->contract_value + (int) $contract->amendments->sum('value_change');

            $cumulative = $this->subledger->certified((int) $line->counterparty_id, $line->contract_id, $context->header->id) + $line->credit;

            if ($limit === null || $cumulative > $limit) {
                $violations[] = $this->violation(['limit' => $limit ?? '—'], $line);
            }
        }

        return $violations;
    }
}
