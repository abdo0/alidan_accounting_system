<?php

declare(strict_types=1);

namespace App\Domain\Controls\Exceptions;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Posting\PostingObserver;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterResolver;
use App\Models\User;

/**
 * Document B §2.2 step 8. A posting raises an exception for:
 *   - a Partial or Missing document status (VR-13), closed when the document arrives;
 *   - any posting to the suspense account (VR-47);
 *   - any non-zero amount difference carried from the source.
 * Clearing the suspense account with a resolution reference closes the exception
 * it names (VR-48).
 */
final class PostingExceptionObserver implements PostingObserver
{
    public function __construct(
        private readonly ExceptionRaiser $raiser,
        private readonly ExceptionLifecycle $lifecycle,
        private readonly ParameterResolver $parameters,
    ) {}

    public function posted(JournalHeader $header, User $actor): void
    {
        $header->loadMissing('lines.account');

        if ($header->doc_status !== DocStatus::Complete) {
            $this->raiser->raise(
                $header->doc_status === DocStatus::Partial ? ExceptionCategory::PartialDocument : ExceptionCategory::MissingDocument,
                __('controls.exception.document', ['jv' => $header->jv_no, 'status' => $header->doc_status->getLabel()]),
                ['journal_header_id' => $header->id, 'amount' => $header->totalDebit()],
                'document:'.$header->id,
            );
        }

        $suspense = $this->parameters->value(ParameterCode::SuspenseAccount, $header->posting_date);

        foreach ($header->lines as $line) {
            if ($line->account->code === $suspense && $line->debit > 0) {
                $this->raiser->raise(
                    ExceptionCategory::SuspenseItem,
                    __('controls.exception.suspense', ['jv' => $header->jv_no, 'account' => $suspense]),
                    ['journal_header_id' => $header->id, 'journal_line_id' => $line->id, 'account_id' => $line->account_id, 'amount' => $line->debit],
                    'suspense:'.$line->id,
                );
            }

            if (($line->amount_difference ?? 0) !== 0) {
                $this->raiser->raise(
                    ExceptionCategory::AmountDifference,
                    __('controls.exception.amount_difference', ['jv' => $header->jv_no, 'line' => $line->line_no]),
                    ['journal_header_id' => $header->id, 'journal_line_id' => $line->id, 'amount' => $line->amount_difference],
                    'difference:'.$line->id,
                );
            }
        }

        if ($header->transactionType->isCode('TT-33')) {
            $this->clearSuspense($header, $actor);
        }
    }

    /** VR-48: the resolution reference names the suspense exception it clears. */
    private function clearSuspense(JournalHeader $header, User $actor): void
    {
        $exceptions = ControlException::query()
            ->where('category', ExceptionCategory::SuspenseItem)
            ->where('status', '!=', 'resolved')
            ->where(fn ($q) => $q->where('exception_no', $header->resolution_ref)
                ->orWhere('source_code', $header->resolution_ref)
                ->when($header->linked_journal_id !== null, fn ($q) => $q->orWhere('journal_header_id', $header->linked_journal_id)))
            ->get();

        foreach ($exceptions as $exception) {
            $this->lifecycle->resolve(null, $exception, __('controls.exception.suspense_cleared', ['jv' => $header->jv_no, 'ref' => $header->resolution_ref]), $actor->id);
        }
    }
}
