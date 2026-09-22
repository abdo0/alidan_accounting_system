<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Funding\ChainStep;
use App\Domain\Ledger\Rules\SelectorParser;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/**
 * CS: a declared funding-chain step must match its account pair (Document B §2.6,
 * RE-05). This is what keeps the three-level control computable from the ledger.
 */
final class ChainStepPair extends BaseRule
{
    public function __construct(private readonly SelectorParser $parser) {}

    public function code(): string
    {
        return 'CS';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        // Historical rows are carried as the source has them; a mismatch there is
        // an exception to review, raised by the importer, not a refusal.
        if ($context->header->is_migration || $context->isType('TT-35')) {
            return [];
        }

        $violations = [];
        $steps = ChainStep::query()->whereIn('id', $context->lines()->pluck('chain_step_id')->filter()->unique())->get()->keyBy('id');

        foreach ($context->lines() as $line) {
            $step = $line->chain_step_id === null ? null : $steps->get($line->chain_step_id);

            if ($step === null) {
                continue;
            }

            $selector = $line->debit > 0 ? $step->debit_selector : $step->credit_selector;

            if (! $this->parser->parse($selector)->contains($context->account($line)->code, $context->originalDebitCodes())) {
                $violations[] = $this->violation(['step' => $step->code, 'account' => $context->account($line)->code], $line);
            }
        }

        return $violations;
    }
}
