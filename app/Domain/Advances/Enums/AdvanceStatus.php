<?php

declare(strict_types=1);

namespace App\Domain\Advances\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived from the outstanding amount and the settlement deadline; never stored.
 */
enum AdvanceStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Open = 'open';
    case Settled = 'settled';
    case Overdue = 'overdue';

    public static function translationKey(): string
    {
        return 'advance_status';
    }
}
