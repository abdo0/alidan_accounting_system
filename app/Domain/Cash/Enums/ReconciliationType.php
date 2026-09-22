<?php

declare(strict_types=1);

namespace App\Domain\Cash\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The reconciliations of M09 and M11.
 */
enum ReconciliationType: string implements HasLabel
{
    use TranslatesEnum;

    case CashCount = 'cash_count';
    case Bank = 'bank';
    case Advance = 'advance';
    case Contractor = 'contractor';
    case Supplier = 'supplier';
    case GovernmentShare = 'government_share';

    public static function translationKey(): string
    {
        return 'reconciliation_type';
    }
}
