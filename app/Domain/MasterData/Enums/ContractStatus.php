<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * A contract whose fields the source marks "to be defined" stays Pending (MIG-09).
 */
enum ContractStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Terminated = 'terminated';

    public static function translationKey(): string
    {
        return 'contract_status';
    }
}
