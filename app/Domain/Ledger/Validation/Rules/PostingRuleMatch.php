<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Rules\PostingRuleResolver;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Enums\ApprovalStatus;

/**
 * PR: the entry's lines must fit a posting rule of its transaction type
 * (Document B §2.4). The engine rejects any line whose account falls outside the
 * permitted set. A rule awaiting approval blocks its transaction type.
 *
 * The resolved rule is placed on the context for the mandatory-dimension check
 * that follows.
 */
final class PostingRuleMatch extends BaseRule
{
    public function __construct(private readonly PostingRuleResolver $resolver) {}

    public function code(): string
    {
        return 'PR';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $header = $context->header;

        // A reversal mirrors an entry that already passed; a migrated entry is the
        // historical record itself. Neither is judged against today's rules.
        if ($context->type()->is_system) {
            return [];
        }

        $effective = $this->resolver->effectiveRules($header);

        if ($effective->isEmpty()) {
            return [$this->violation(['type' => $context->type()->code], null, 'none')];
        }

        if ($effective->every(fn ($rule): bool => $rule->approval_status === ApprovalStatus::Pending)) {
            return [$this->violation(['type' => $context->type()->code, 'rules' => $effective->pluck('code')->implode(', ')], null, 'pending')];
        }

        $matching = $this->resolver->matching($header, $context->originalDebitCodes());

        if ($matching->isEmpty()) {
            return [$this->violation([
                'type' => $context->type()->code,
                'rules' => $effective->pluck('code')->implode(', '),
            ])];
        }

        $chosen = $header->posting_rule_id === null
            ? ($matching->count() === 1 ? $matching->first() : null)
            : $matching->firstWhere('id', $header->posting_rule_id);

        if ($chosen === null) {
            return [$this->violation(['rules' => $matching->pluck('code')->implode(', ')], null, 'ambiguous')];
        }

        $context->postingRule = $chosen;

        return [];
    }
}
