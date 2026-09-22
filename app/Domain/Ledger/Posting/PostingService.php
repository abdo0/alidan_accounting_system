<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Posting;

use App\Domain\Ledger\Enums\ApprovalAction;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\ValidationChain;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Shared\Approval;
use App\Domain\Shared\Audit\AuditRecorder;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;

/**
 * Posts an approved entry (Document B §2.2).
 *
 *   1-3  resolve the posting rule and the period, run the validation chain
 *   4    run the posting guards (duplicate detection)
 *   5    draw the JV number from the gapless sequence
 *   6    persist header and status in one transaction
 *   7    write the approvals and audit rows
 *   8    let the observers raise exceptions and open subledger records
 *   9    commit
 *
 * Nothing is written when a rule fails except a rejected-attempt audit row.
 */
final class PostingService
{
    /**
     * @param  iterable<PostingGuard>  $guards
     * @param  iterable<PostingObserver>  $observers
     */
    public function __construct(
        private readonly ValidationChain $chain,
        private readonly JvNumberAllocator $numbers,
        private readonly EntryHasher $hasher,
        private readonly AuditRecorder $audit,
        private readonly iterable $guards = [],
        private readonly iterable $observers = [],
    ) {}

    public function post(User $actor, JournalHeader $header): JournalHeader
    {
        try {
            return DatabaseContext::withAudit('journal_post', null, fn (): JournalHeader => $this->postWithinTransaction($actor, $header));
        } catch (JournalRejected $rejected) {
            $this->audit->event('journal_post_rejected', 'journal_headers', $header->id, $rejected->getMessage(), ['rules' => $rejected->rules()], $actor->id);

            throw $rejected;
        }
    }

    private function postWithinTransaction(User $actor, JournalHeader $header): JournalHeader
    {
        /** @var JournalHeader $header */
        $header = JournalHeader::query()->lockForUpdate()->findOrFail($header->id);

        // Held FOR SHARE so a period cannot be closed under a posting in flight.
        AccountingPeriod::query()->whereKey($header->period_id)->sharedLock()->first();

        $context = new PostingContext($header, $actor, Checkpoint::Post);
        $result = $this->chain->runContext($context);
        $findings = [...$result->blocking(), ...$result->unacknowledgedWarnings($header->acknowledged_warnings ?? [])];

        foreach ($this->guards as $guard) {
            $findings = [...$findings, ...$guard->beforePosting($context)];
        }

        if ($findings !== []) {
            throw new JournalRejected($findings);
        }

        $from = $header->status->value;
        $jvNo = $header->jv_no ?? $this->numbers->next($header);
        $chain = $this->hasher->chain($header, $jvNo);

        $header->forceFill([
            'jv_no' => $jvNo,
            'posting_rule_id' => $context->postingRule->id ?? $header->posting_rule_id,
            'status' => JournalStatus::Posted,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'entry_hash' => $chain['hash'],
            'prev_entry_hash' => $chain['prev'],
        ])->save();

        Approval::record($header, ApprovalAction::Post, $actor, $from, JournalStatus::Posted->value);

        foreach ($this->observers as $observer) {
            $observer->posted($header, $actor);
        }

        return $header;
    }

    /**
     * The findings the chain would return at posting, without posting.
     *
     * @return list<Violation>
     */
    public function precheck(User $actor, JournalHeader $header): array
    {
        $result = $this->chain->run($header, $actor, Checkpoint::Post);

        return $result->violations;
    }

    /** Whether the ledger, all of it, still balances. Used by the integrity report. */
    public static function ledgerDifference(): int
    {
        return (int) DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS diff')
            ->value('diff');
    }
}
