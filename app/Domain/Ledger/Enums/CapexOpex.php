<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/** Document C tab 10, CAPEX / OPEX. */
enum CapexOpex: string implements HasLabel
{
    use TranslatesEnum;

    case Capex = 'capex';
    case Opex = 'opex';
    case NotApplicable = 'na';

    public static function translationKey(): string
    {
        return 'capex_opex';
    }
}
