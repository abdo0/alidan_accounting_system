<?php

declare(strict_types=1);

namespace App\Domain\Controls\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The categories of the exceptions register (Document C tab 24) and of the exceptions the engine raises itself (Document B §2.2 step 8).
 */
enum ExceptionCategory: string implements HasLabel
{
    use TranslatesEnum;

    case OpenControlDifference = 'open_control_difference';
    case StageDifference = 'stage_difference';
    case UnidentifiedReceipt = 'unidentified_receipt';
    case ProbableDuplicate = 'probable_duplicate';
    case PotentialDuplicates = 'potential_duplicates';
    case SuspenseItem = 'suspense_item';
    case ReviewRequired = 'review_required';
    case ClassificationReview = 'classification_review';
    case UnpricedSourceLine = 'unpriced_source_line';
    case UndeterminedReclassification = 'undetermined_reclassification';
    case AmountUnderReview = 'amount_under_review';
    case ContractorAccountOpen = 'contractor_account_open';
    case UndatedEntries = 'undated_entries';
    case InconsistentDates = 'inconsistent_dates';
    case AnonymisedPayees = 'anonymised_payees';
    case PendingEvidence = 'pending_evidence';
    case ReferenceDataGap = 'reference_data_gap';
    case MissingDimension = 'missing_dimension';
    case SourceIncomplete = 'source_incomplete';
    case ContractDataMissing = 'contract_data_missing';
    case MissingDocument = 'missing_document';
    case PartialDocument = 'partial_document';
    case AmountDifference = 'amount_difference';
    case OverdueAdvance = 'overdue_advance';
    case CashVariance = 'cash_variance';
    case SourceException = 'source_exception';

    public static function translationKey(): string
    {
        return 'exception_category';
    }
}
