<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Contractors\SubledgerQuery;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\MasterData\Contract;

/**
 * VR-33 (warning): retention withheld should equal the contract retention
 * percentage applied to the certified amount. Compared cumulatively on the
 * contract, since a retention entry stands apart from the certificate it follows.
 */
final class Vr33RetentionMatchesContract extends BaseRule
{
    public function __construct(private readonly SubledgerQuery $subledger) {}

    public function code(): string
    {
        return 'VR-33';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function check(PostingContext $context): array
    {
        if (! $context->isType('TT-17')) {
            return [];
        }

        $accounts = $this->subledger->accounts();
        $violations = [];

        foreach ($context->creditLines()->filter(fn ($l): bool => in_array($l->account_id, $accounts['retention'], true) && $l->contract_id !== null && $l->counterparty_id !== null) as $line) {
            $contract = Contract::query()->find($line->contract_id);

            if ($contract?->retention_pct === null) {
                continue;
            }

            $certified = $this->subledger->certified((int) $line->counterparty_id, $line->contract_id);
            $expected = (int) round($certified * (float) $contract->retention_pct / 100);
            $actual = $this->subledger->sumOn($accounts['retention'], 'credit', (int) $line->counterparty_id, $line->contract_id, $context->header->id) + $line->credit;

            if ($actual !== $expected) {
                $violations[] = $this->violation(['actual' => $actual, 'expected' => $expected], $line);
            }
        }

        return $violations;
    }
}
