<?php

declare(strict_types=1);

namespace App\Domain\Funding\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether a source funding reference reached the ledger.
 */
enum FundingBatchStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Posted = 'posted';
    case Held = 'held';
    case ReviewRequired = 'review_required';
    case Excluded = 'excluded';

    public static function translationKey(): string
    {
        return 'funding_batch_status';
    }
}
