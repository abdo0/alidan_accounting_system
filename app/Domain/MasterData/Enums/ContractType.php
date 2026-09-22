<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of contract the register holds.
 */
enum ContractType: string implements HasLabel
{
    use TranslatesEnum;

    case Concession = 'concession';
    case Construction = 'construction';
    case Supply = 'supply';
    case Services = 'services';
    case Consulting = 'consulting';
    case Other = 'other';

    public static function translationKey(): string
    {
        return 'contract_type';
    }
}
