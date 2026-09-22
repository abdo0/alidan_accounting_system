<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * A fiscal year closes only after the year-end result transfer (TT-38, VR-53).
 */
enum FiscalYearStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Open = 'open';
    case Closed = 'closed';

    public static function translationKey(): string
    {
        return 'fiscal_year_status';
    }
}
