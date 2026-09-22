<?php

declare(strict_types=1);

namespace App\Domain\Revenue;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalService;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Counterparty;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\ParameterResolver;
use App\Domain\Reporting\AccountSets;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The government revenue share (M14, Document B §4.10):
 *
 *   eligible_revenue = Σ credits to the revenue accounts where revenue_eligible,
 *                      posted inside the period
 *   share_due        = eligible_revenue × gov_share_rate (effective at period end)
 *   recognition      : Dr the share expense / Cr the share payable  (PR-25)
 *
 * The rate is read from the parameters table and nowhere else (VR-43). Funding,
 * shareholder loans and every line flagged not eligible are outside the base
 * (VR-44): a funding transaction never credits a revenue account (VR-20), so the
 * base is revenue lines alone. The accounts come from the posting rules, not from
 * this class.
 */
final class GovernmentShareService
{
    public function __construct(
        private readonly ParameterResolver $parameters,
        private readonly AccountSets $sets,
        private readonly JournalService $journals,
    ) {}

    /** @return array{eligible: int, excluded: int, rate: string, due: int, recognised: int, to_recognise: int} */
    public function compute(AccountingPeriod $period, ?int $projectId = null): array
    {
        $rate = $this->parameters->decimal(ParameterCode::GovShareRate, $period->ends_on, $projectId)
            ?? throw RuleViolation::because('VR-43', 'validation_rules.VR-43');

        $revenue = $this->sets->ofRule('PR-24', 'credit');

        $base = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->whereBetween('jh.posting_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])
            ->whereIn('jl.account_id', $revenue)
            ->when($projectId !== null, fn ($q) => $q->where('jl.project_id', $projectId));

        $eligible = (int) (clone $base)->where('jl.revenue_eligible', true)->selectRaw('coalesce(sum(jl.credit) - sum(jl.debit), 0) AS n')->value('n');
        $excluded = (int) (clone $base)->where('jl.revenue_eligible', false)->selectRaw('coalesce(sum(jl.credit) - sum(jl.debit), 0) AS n')->value('n');

        $due = (int) bcadd(bcmul((string) $eligible, $rate, 4), '0.5', 0);

        $recognised = (int) DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->whereIn('jh.status', JournalStatus::ledgerValues())
            ->where('jh.period_id', $period->id)
            ->whereIn('jl.account_id', $this->sets->ofRule('PR-25', 'debit'))
            ->when($projectId !== null, fn ($q) => $q->where('jl.project_id', $projectId))
            ->selectRaw('coalesce(sum(jl.debit) - sum(jl.credit), 0) AS n')
            ->value('n');

        return [
            'eligible' => $eligible,
            'excluded' => $excluded,
            'rate' => $rate,
            'due' => $due,
            'recognised' => $recognised,
            'to_recognise' => $due - $recognised,
        ];
    }

    /**
     * Prepares the TT-28 recognition entry as a Draft for the difference still to
     * recognise. It then goes through review and approval like any other entry.
     */
    public function prepareRecognition(User $actor, AccountingPeriod $period, int $projectId, int $costCenterId): JournalHeader
    {
        $figures = $this->compute($period, $projectId);

        if ($figures['to_recognise'] <= 0) {
            throw RuleViolation::because('M14', 'rules.revenue.nothing_to_recognise');
        }

        $expense = $this->sets->ofRule('PR-25', 'debit')[0] ?? throw RuleViolation::because('PR', 'validation_rules.PR_none', ['type' => 'TT-28']);
        $payable = $this->sets->ofRule('PR-25', 'credit')[0] ?? throw RuleViolation::because('PR', 'validation_rules.PR_none', ['type' => 'TT-28']);
        $government = Counterparty::query()->where('is_government', true)->orderBy('id')->value('id');

        $dimensions = ['project_id' => $projectId, 'cost_center_id' => $costCenterId];

        return $this->journals->saveDraft($actor, [
            'transaction_type_id' => TransactionType::query()->where('code', 'TT-28')->value('id'),
            'posting_date' => $period->ends_on->toDateString(),
            'txn_date' => $period->ends_on->toDateString(),
            'description_ar' => __('rules.revenue.recognition_description', ['period' => $period->period_code, 'rate' => $figures['rate']], 'ar'),
            'description_en' => __('rules.revenue.recognition_description', ['period' => $period->period_code, 'rate' => $figures['rate']], 'en'),
            'doc_status' => 'complete',
            'doc_ref' => 'GOV-SHARE-'.$period->period_code,
        ], [
            ['account_id' => $expense, 'debit' => $figures['to_recognise'], 'credit' => 0, 'capex_opex' => 'opex'] + $dimensions,
            ['account_id' => $payable, 'debit' => 0, 'credit' => $figures['to_recognise'], 'counterparty_id' => $government] + $dimensions,
        ]);
    }
}
