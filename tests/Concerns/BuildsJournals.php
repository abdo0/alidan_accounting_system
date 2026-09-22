<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalService;
use App\Domain\Ledger\TransactionType;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Models\User;

/**
 * Builds entries against the seeded SHH-01 chart and walks them through the real
 * workflow: an Accountant prepares, a Senior Accountant reviews, the Finance
 * Manager approves and posts. Nothing here bypasses a rule.
 */
trait BuildsJournals
{
    use ActsAsRole;

    private ?User $maker = null;

    private ?User $reviewer = null;

    private ?User $manager = null;

    protected function maker(): User
    {
        return $this->maker ??= $this->userWithRole('accountant');
    }

    protected function reviewer(): User
    {
        return $this->reviewer ??= $this->userWithRole('senior_accountant');
    }

    protected function manager(): User
    {
        return $this->manager ??= $this->userWithRole('finance_manager');
    }

    protected function accountId(string $code): int
    {
        return (int) Account::query()->where('code', $code)->value('id');
    }

    protected function projectId(string $code = 'PRJ-01'): int
    {
        return (int) Project::query()->where('code', $code)->value('id');
    }

    protected function costCenterId(string $code = 'CC-PM'): int
    {
        return (int) CostCenter::query()->where('code', $code)->value('id');
    }

    protected function counterpartyId(string $code): int
    {
        return (int) Counterparty::query()->where('code', $code)->value('id');
    }

    protected function cashId(string $glCode = '111002'): int
    {
        return (int) BankAccount::query()->where('code', $glCode)->value('id');
    }

    protected function rcId(string $code): int
    {
        return (int) ResponsibilityCenter::query()->where('code', $code)->value('id');
    }

    /**
     * A line with the project and cost centre every line needs (VR-05).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function line(string $account, int $debit = 0, int $credit = 0, array $extra = []): array
    {
        return $extra + [
            'account_id' => $this->accountId($account),
            'debit' => $debit,
            'credit' => $credit,
            'project_id' => $this->projectId(),
            'cost_center_id' => $this->costCenterId(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     */
    protected function draft(string $type, array $lines, array $header = [], ?User $maker = null): JournalHeader
    {
        return app(JournalService::class)->saveDraft($maker ?? $this->maker(), $header + [
            'transaction_type_id' => TransactionType::query()->where('code', $type)->value('id'),
            'posting_date' => '2026-03-15',
            'txn_date' => '2026-03-15',
            'description_ar' => 'قيد اختبار',
            'doc_status' => 'complete',
            'doc_ref' => 'DOC-1',
        ], $lines);
    }

    /** @param  list<string>  $acknowledge */
    protected function submitReviewApprove(JournalHeader $journal, array $acknowledge = [], ?string $approvalRef = null): JournalHeader
    {
        $workflow = app(JournalWorkflow::class);
        $journal = $workflow->submit($this->maker(), $journal, $acknowledge);

        if ($journal->transactionType->requires_review) {
            $journal = $workflow->review($this->reviewer(), $journal);
        }

        return $workflow->approve($this->manager(), $journal, $approvalRef);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     * @param  list<string>  $acknowledge
     */
    protected function postEntry(string $type, array $lines, array $header = [], array $acknowledge = [], ?string $approvalRef = null): JournalHeader
    {
        $journal = $this->submitReviewApprove($this->draft($type, $lines, $header), $acknowledge, $approvalRef);

        return app(JournalWorkflow::class)->post($this->manager(), $journal);
    }

    /**
     * TT-01: shareholder funding into the project safe (PR-01).
     *
     * @return list<array<string, mixed>>
     */
    protected function fundingLines(int $amount): array
    {
        return [
            $this->line('111002', debit: $amount, extra: ['cash_account_id' => $this->cashId('111002')]),
            $this->line('221001', credit: $amount, extra: ['counterparty_id' => $this->counterpartyId('SH-01'), 'funding_source_id' => FundingSource::query()->where('code', 'FS-SH-01')->value('id')]),
        ];
    }
}
