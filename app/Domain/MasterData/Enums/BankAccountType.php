<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document C tab 09 "Type".
 */
enum BankAccountType: string implements HasLabel
{
    use TranslatesEnum;

    case Cash = 'cash';
    case Safe = 'safe';
    case Bank = 'bank';
    case Transit = 'transit';

    public static function translationKey(): string
    {
        return 'bank_account_type';
    }
}
