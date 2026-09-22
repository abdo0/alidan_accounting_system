<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Contract amendments and change orders (M13).
 */
enum AmendmentType: string implements HasLabel
{
    use TranslatesEnum;

    case Amendment = 'amendment';
    case ChangeOrder = 'change_order';

    public static function translationKey(): string
    {
        return 'amendment_type';
    }
}
