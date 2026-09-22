<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Advances\Advance;
use App\Domain\Cash\Reconciliation;
use App\Domain\Closing\ChecklistTask;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\DuplicateFlag;
use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingBatch;
use App\Domain\Funding\FundingCategory;
use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\PostingRule;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\ChangeRequest;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\Shareholder;
use App\Domain\MasterData\ValueListItem;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Domain\Organisation\FiscalYear;
use App\Domain\Organisation\Parameter;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\ChangeRequestPolicy;
use App\Policies\ControlsPolicy;
use App\Policies\JournalHeaderPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\ParameterPolicy;
use App\Policies\PeriodPolicy;
use App\Policies\ReferenceDataPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Registered explicitly rather than discovered. Laravel's convention looks for
     * App\Policies\<Model>Policy by swapping a "Models\" segment in the model's
     * namespace -- these models live under App\Domain\*, so discovery finds nothing
     * and every gate would silently fall through to "allowed".
     *
     * @var array<class-string, class-string>
     */
    public const POLICIES = [
        Account::class => AccountPolicy::class,
        AccountingPeriod::class => PeriodPolicy::class,
        ChecklistTask::class => PeriodPolicy::class,
        BankAccount::class => MasterDataPolicy::class,
        Advance::class => ControlsPolicy::class,
        Reconciliation::class => ControlsPolicy::class,
        ChainStep::class => ReferenceDataPolicy::class,
        ChangeRequest::class => ChangeRequestPolicy::class,
        Company::class => ReferenceDataPolicy::class,
        Contract::class => MasterDataPolicy::class,
        ControlException::class => ControlsPolicy::class,
        DuplicateFlag::class => ControlsPolicy::class,
        CostCenter::class => MasterDataPolicy::class,
        Counterparty::class => MasterDataPolicy::class,
        FiscalYear::class => PeriodPolicy::class,
        FundingBatch::class => MasterDataPolicy::class,
        FundingCategory::class => ReferenceDataPolicy::class,
        FundingSource::class => MasterDataPolicy::class,
        JournalHeader::class => JournalHeaderPolicy::class,
        TransactionType::class => ReferenceDataPolicy::class,
        PostingRule::class => ReferenceDataPolicy::class,
        Parameter::class => ParameterPolicy::class,
        Project::class => MasterDataPolicy::class,
        ResponsibilityCenter::class => MasterDataPolicy::class,
        Shareholder::class => ReferenceDataPolicy::class,
        User::class => UserPolicy::class,
        ValueListItem::class => ReferenceDataPolicy::class,
    ];

    /**
     * The abilities a read-only role keeps. Everything else is refused before the
     * policy is consulted. The Internal Auditor "may comment but not change"
     * (Document C tab 19), so commenting is a read ability here.
     */
    private const READ_ABILITIES = ['viewAny', 'view', 'export', 'print', 'comment'];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Enforced here, once, because a rule spread across many policies is a rule
        // that one of them will forget. Returning null falls through to the policy;
        // false refuses outright.
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->isReadOnly() && ! in_array($ability, self::READ_ABILITIES, true)
                ? false
                : null;
        });
    }
}
