<?php

declare(strict_types=1);

namespace App\Domain\Funding\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document C tab 20 funding_sources.
 */
enum FundingSourceType: string implements HasLabel
{
    use TranslatesEnum;

    case Shareholder = 'shareholder';
    case ThirdParty = 'third_party';
    case Recovery = 'recovery';
    case RecycledRecovery = 'recycled_recovery';

    public static function translationKey(): string
    {
        return 'funding_source_type';
    }
}
