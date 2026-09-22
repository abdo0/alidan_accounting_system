<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The counterparty sub-types of RE-02.
 */
enum CounterpartyType: string implements HasLabel
{
    use TranslatesEnum;

    case Contractor = 'contractor';
    case Supplier = 'supplier';
    case Employee = 'employee';
    case AdvanceHolder = 'advance_holder';
    case Shareholder = 'shareholder';
    case Government = 'government';
    case Other = 'other';

    public static function translationKey(): string
    {
        return 'counterparty_type';
    }
}
