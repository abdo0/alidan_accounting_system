<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Enums\ApprovalAction;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\TransactionType;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Shared\Approval;
use App\Models\User;
use App\Support\DatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * TT-35 (Document B §2.7). A posted entry is corrected by a mirror entry with the
 * same dimensions, a mandatory reason and a posting date in an open period. The
 * original is marked Reversed; both stay visible everywhere and together net to nil.
 */
final class ReversalService
{
    public function __construct(private readonly PostingService $posting) {}

    public function reverse(User $actor, JournalHeader $original, string $reason, ?CarbonImmutable $postingDate = null): JournalHeader
    {
        if (! $actor->hasPermission('journal.reverse') || ! $actor->hasSatisfiedMfaRequirement()) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }

        if ($original->status !== JournalStatus::Posted) {
            throw new JournalRejected([new Violation('VR-51', Severity::Blocking, __('validation_rules.VR-51_status'))]);
        }

        if (trim($reason) === '') {
            throw new JournalRejected([new Violation('VR-51', Severity::Blocking, __('validation_rules.VR-51'))]);
        }

        $postingDate ??= CarbonImmutable::today();
        $period = AccountingPeriod::containing($original->company_id, $postingDate);

        if ($period === null || ! $period->status->acceptsOrdinaryPosting()) {
            throw new JournalRejected([new Violation('VR-52', Severity::Blocking, __('validation_rules.VR-52', ['date' => $postingDate->toDateString()]))]);
        }

        return DatabaseContext::withAudit('journal_reverse', $reason, function () use ($actor, $original, $reason, $postingDate, $period): JournalHeader {
            $original->loadMissing('lines');

            $mirror = JournalHeader::query()->create([
                'company_id' => $original->company_id,
                'period_id' => $period->id,
                'transaction_type_id' => TransactionType::query()->where('code', TransactionType::REVERSAL)->value('id'),
                'txn_date' => $postingDate,
                'posting_date' => $postingDate,
                'description_ar' => __('rules.journal.reversal_description', ['jv' => $original->jv_no], 'ar'),
                'description_en' => __('rules.journal.reversal_description', ['jv' => $original->jv_no], 'en'),
                'date_status' => 'ok',
                'doc_status' => 'complete',
                'doc_ref' => $original->jv_no,
                'source_reference' => $original->source_reference,
                'status' => JournalStatus::Draft,
                'reversal_of_journal_id' => $original->id,
                'linked_journal_id' => $original->id,
                'reversal_reason' => $reason,
                'created_by' => $actor->id,
            ]);

            foreach ($original->lines as $line) {
                $mirror->lines()->create([
                    ...$line->only(JournalLine::DIMENSIONS),
                    'line_no' => $line->line_no,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'advance_id' => $line->advance_id,
                ]);
            }

            $mirror->unsetRelation('lines');
            $posted = $this->posting->post($actor, $mirror);

            $original->forceFill([
                'status' => JournalStatus::Reversed,
                'reversed_by_journal_id' => $posted->id,
            ])->save();

            Approval::record($original, ApprovalAction::Reverse, $actor, JournalStatus::Posted->value, JournalStatus::Reversed->value, $reason);

            return $posted;
        });
    }
}
