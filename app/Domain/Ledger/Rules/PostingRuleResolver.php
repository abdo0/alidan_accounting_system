<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Rules;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\PostingRule;
use App\Domain\MasterData\Enums\ApprovalStatus;
use Illuminate\Support\Collection;

/**
 * Finds the posting rules of the entry's transaction type, effective at its posting
 * date, whose selectors admit every line: each debit account in the rule's debit
 * set and each credit account in its credit set (Document B §2.4).
 *
 * Document C writes a rule's condition in prose, so when one transaction type has
 * several rules (TT-01 loan / current account / capital) the lines decide; if more
 * than one still fits, the user chooses and the choice is stored on the header.
 */
final class PostingRuleResolver
{
    public function __construct(private readonly SelectorParser $parser) {}

    /** @return Collection<int, PostingRule> rules effective for the entry, approved or not */
    public function effectiveRules(JournalHeader $header): Collection
    {
        return PostingRule::query()
            ->where('transaction_type_id', $header->transaction_type_id)
            ->orderBy('code')
            ->get()
            ->filter(fn (PostingRule $rule): bool => $rule->isEffectiveOn($header->posting_date))
            ->values();
    }

    /**
     * @param  list<string>  $originalDebitCodes
     * @return Collection<int, PostingRule>
     */
    public function matching(JournalHeader $header, array $originalDebitCodes = []): Collection
    {
        return $this->effectiveRules($header)
            ->filter(fn (PostingRule $rule): bool => $rule->approval_status === ApprovalStatus::Approved)
            ->filter(fn (PostingRule $rule): bool => $this->admits($rule, $header, $originalDebitCodes))
            ->values();
    }

    /** @param  list<string>  $originalDebitCodes */
    public function admits(PostingRule $rule, JournalHeader $header, array $originalDebitCodes = []): bool
    {
        $debits = $this->parser->parse($rule->debit_selector);
        $credits = $this->parser->parse($rule->credit_selector);

        return $header->lines->every(function (JournalLine $line) use ($debits, $credits, $originalDebitCodes): bool {
            $code = $line->account->code;

            return $line->debit > 0
                ? $debits->contains($code, $originalDebitCodes)
                : $credits->contains($code, $originalDebitCodes);
        });
    }
}
