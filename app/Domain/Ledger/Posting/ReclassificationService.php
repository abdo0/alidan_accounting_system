<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\JournalService;
use App\Domain\Ledger\TransactionType;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\MasterData\Account;
use App\Models\User;

/**
 * TT-34 (Document B §2.7). Prepares the correcting entry for a posted line that
 * carries the wrong account:
 *
 *   Dr the correct account / Cr the account originally debited
 *
 * with the same amount, dimensions, transaction date, description and source
 * reference, linked to the original. It is a Draft: it then goes through the normal
 * workflow and needs the approval reference (board decision where material). The
 * rules that guard it are VR-49 (never credit an advance a second time) and VR-50
 * (change nothing but the account).
 */
final class ReclassificationService
{
    public function __construct(private readonly JournalService $journals) {}

    public function prepare(User $actor, JournalLine $originalLine, Account $correctAccount, string $approvalRef): JournalHeader
    {
        $original = $originalLine->header()->with('lines')->firstOrFail();

        if (! $original->status->isInLedger() || $original->status === JournalStatus::Reversed || $originalLine->debit === 0) {
            throw new JournalRejected([new Violation('VR-50', Severity::Blocking, __('validation_rules.VR-50_link'))]);
        }

        $dimensions = $originalLine->only(JournalLine::DIMENSIONS);

        return $this->journals->saveDraft($actor, [
            'transaction_type_id' => TransactionType::query()->where('code', TransactionType::RECLASSIFICATION)->value('id'),
            'posting_date' => now()->toDateString(),
            'txn_date' => $original->txn_date?->toDateString(),
            'date_status' => $original->date_status->value,
            'description_ar' => $original->description_ar,
            'description_en' => $original->description_en,
            'source_reference' => $original->source_reference,
            'doc_status' => 'complete',
            'doc_ref' => $original->jv_no,
            'approval_ref' => $approvalRef,
            'linked_journal_id' => $original->id,
        ], [
            ['account_id' => $correctAccount->id, 'debit' => $originalLine->debit, 'credit' => 0] + $dimensions,
            ['account_id' => $originalLine->account_id, 'debit' => 0, 'credit' => $originalLine->debit] + $dimensions,
        ]);
    }
}
