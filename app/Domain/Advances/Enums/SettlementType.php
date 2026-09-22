<?php

declare(strict_types=1);

namespace App\Domain\Advances\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * How an amount left an advance (Document B §4.3: settlements, refunds, reclassifications, sub-advances passed on).
 */
enum SettlementType: string implements HasLabel
{
    use TranslatesEnum;

    case Capex = 'capex';
    case Opex = 'opex';
    case FixedAsset = 'fixed_asset';
    case Acquisition = 'acquisition';
    case Refund = 'refund';
    case Reclassification = 'reclassification';
    case SubAdvance = 'sub_advance';
    case Shortfall = 'shortfall';
    case Recovery = 'recovery';
    case Other = 'other';

    public static function translationKey(): string
    {
        return 'settlement_type';
    }
}
