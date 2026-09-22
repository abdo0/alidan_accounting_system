<?php

declare(strict_types=1);

/*
 * Application wiring for the SHH-01 engine. Business values -- rates, thresholds,
 * dates -- are never here: they live in the parameters table (Document B §4.10,
 * acceptance criterion 12). This file only says which classes take part.
 */

use App\Domain\Advances\AdvanceObserver;
use App\Domain\Closing\Gates\CashCustody;
use App\Domain\Closing\Gates\ChecklistComplete;
use App\Domain\Closing\Gates\DuplicatesDispositioned;
use App\Domain\Closing\Gates\NoBlockingException;
use App\Domain\Closing\Gates\NothingUnposted;
use App\Domain\Controls\Duplicates\DuplicateGuard;
use App\Domain\Controls\Exceptions\PostingExceptionObserver;
use App\Domain\Ledger\Validation\Rules;
use App\Domain\Reporting\Reports\Rpt01ChartOfAccounts;
use App\Domain\Reporting\Reports\Rpt02MasterData;
use App\Domain\Reporting\Reports\Rpt03Journal;
use App\Domain\Reporting\Reports\Rpt04TrialBalance;
use App\Domain\Reporting\Reports\Rpt05AccountLedger;
use App\Domain\Reporting\Reports\Rpt06FinancialPosition;
use App\Domain\Reporting\Reports\Rpt07ProfitAndLoss;
use App\Domain\Reporting\Reports\Rpt08bFundingSummary;
use App\Domain\Reporting\Reports\Rpt10CashPosition;
use App\Domain\Reporting\Reports\Rpt11ShareholderFunding;
use App\Domain\Reporting\Reports\Rpt12FundingUtilisation;
use App\Domain\Reporting\Reports\Rpt13AdvancesOutstanding;
use App\Domain\Reporting\Reports\Rpt14AdvanceAging;
use App\Domain\Reporting\Reports\Rpt15SettlementAudit;
use App\Domain\Reporting\Reports\Rpt16ContractorSubledger;
use App\Domain\Reporting\Reports\Rpt17ContractorReconciliation;
use App\Domain\Reporting\Reports\Rpt18CipRegister;
use App\Domain\Reporting\Reports\Rpt19GovernmentShare;
use App\Domain\Reporting\Reports\Rpt20FundingChain;
use App\Domain\Reporting\Reports\Rpt22Duplicates;
use App\Domain\Reporting\Reports\Rpt23Unposted;
use App\Domain\Reporting\Reports\Rpt24Exceptions;
use App\Domain\Reporting\Reports\Rpt26ChangeLog;
use App\Domain\Reporting\Reports\Rpt27Dashboard;
use App\Domain\Reporting\Reports\Rpt28ClosingStatus;
use App\Domain\Reporting\Reports\Rpt30ProjectCost;
use App\Domain\Reporting\Reports\Rpt32AuditTrail;

return [
    /*
     * The validation chain of Document B §2.3. Order within the list does not
     * matter: the chain sorts by rule group, then code.
     */
    'validation_rules' => [
        Rules\Vr01Balanced::class,
        Rules\Vr02TwoSides::class,
        Rules\Vr03OneSide::class,
        Rules\Vr19WholeDinars::class,
        Rules\Vr04PostingAccount::class,
        Rules\Vr38NonPostingParent::class,
        Rules\Vr09OpenPeriod::class,
        Rules\Vr12Dates::class,
        Rules\Vr53YearEndAfterFinalClose::class,
        Rules\Vr05ProjectCostCentre::class,
        Rules\Vr06CashAccount::class,
        Rules\Vr07AdvanceHolder::class,
        Rules\Vr08Counterparty::class,
        Rules\Vr28PersonalReceivable::class,
        Rules\Vr29ContractReference::class,
        Rules\Vr42RevenueEligibility::class,
        Rules\PostingRuleMatch::class,
        Rules\MandatoryDimensions::class,
        Rules\ChainStepPair::class,
        Rules\Vr11DirectPaymentNoCash::class,
        Rules\Vr20FundingIsNotRevenue::class,
        Rules\Vr21ShareholderClaim::class,
        Rules\Vr22RevenueAccounts::class,
        Rules\Vr23TransferBetweenCash::class,
        Rules\Vr24NoSelfTransfer::class,
        Rules\Vr25AdvanceIsAnAsset::class,
        Rules\Vr26SettlementCreditsAdvance::class,
        Rules\Vr27NoCostCredited::class,
        Rules\Vr31CertifiedWithinContract::class,
        Rules\Vr32RecoveryWithinAdvance::class,
        Rules\Vr33RetentionMatchesContract::class,
        Rules\Vr34PaymentWithinPayable::class,
        Rules\Vr35InvoiceOnce::class,
        Rules\Vr36AcquisitionNotExpensed::class,
        Rules\Vr37AcquisitionBoardResolution::class,
        Rules\Vr39CommercialOperation::class,
        Rules\Vr40AmortizationStart::class,
        Rules\Vr41OneRunPerPeriod::class,
        Rules\Vr44ExclusionReason::class,
        Rules\Vr45AccrualReversal::class,
        Rules\Vr46PrepaymentRelease::class,
        Rules\Vr48SuspenseResolution::class,
        Rules\Vr49NoSecondAdvanceCredit::class,
        Rules\Vr50ReclassificationUnchanged::class,
        Rules\Vr13DocumentStatus::class,
        Rules\Vr14DocumentReference::class,
        Rules\Vr15NoFabricatedDate::class,
        Rules\Vr16MakerChecker::class,
        Rules\Vr17PostPermission::class,
        Rules\BoardApproval::class,
    ],

    /* Checks run inside the posting transaction after the chain (Document B §2.2 step 4). */
    'posting_guards' => [
        DuplicateGuard::class,
    ],

    /* Work done inside the posting transaction once the entry is posted (step 8). */
    'posting_observers' => [
        PostingExceptionObserver::class,
        AdvanceObserver::class,
    ],

    /* The reports of Document C tab 16 built so far, by code. */
    'reports' => [
        'RPT-01' => Rpt01ChartOfAccounts::class,
        'RPT-02' => Rpt02MasterData::class,
        'RPT-03' => Rpt03Journal::class,
        'RPT-04' => Rpt04TrialBalance::class,
        'RPT-05' => Rpt05AccountLedger::class,
        'RPT-06' => Rpt06FinancialPosition::class,
        'RPT-07' => Rpt07ProfitAndLoss::class,
        'RPT-08b' => Rpt08bFundingSummary::class,
        'RPT-10' => Rpt10CashPosition::class,
        'RPT-11' => Rpt11ShareholderFunding::class,
        'RPT-12' => Rpt12FundingUtilisation::class,
        'RPT-13' => Rpt13AdvancesOutstanding::class,
        'RPT-14' => Rpt14AdvanceAging::class,
        'RPT-15' => Rpt15SettlementAudit::class,
        'RPT-16' => Rpt16ContractorSubledger::class,
        'RPT-17' => Rpt17ContractorReconciliation::class,
        'RPT-18' => Rpt18CipRegister::class,
        'RPT-19' => Rpt19GovernmentShare::class,
        'RPT-20' => Rpt20FundingChain::class,
        'RPT-22' => Rpt22Duplicates::class,
        'RPT-23' => Rpt23Unposted::class,
        'RPT-24' => Rpt24Exceptions::class,
        'RPT-26' => Rpt26ChangeLog::class,
        'RPT-27' => Rpt27Dashboard::class,
        'RPT-28' => Rpt28ClosingStatus::class,
        'RPT-30' => Rpt30ProjectCost::class,
        'RPT-32' => Rpt32AuditTrail::class,
    ],

    /* Final-close gates (Document B §4.11). */
    'close_gates' => [
        ChecklistComplete::class,
        NothingUnposted::class,
        DuplicatesDispositioned::class,
        NoBlockingException::class,
        CashCustody::class,
    ],

    'security' => [
        // null: a locked account stays locked until a System Administrator unlocks it.
        'lockout_minutes' => null,
    ],

    'documents' => [
        'disk' => 'documents',
        'max_kilobytes' => 20480,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'xlsx', 'xls', 'docx', 'doc', 'csv'],
    ],
];
