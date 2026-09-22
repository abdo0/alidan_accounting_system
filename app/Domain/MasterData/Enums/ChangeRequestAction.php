<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * What a change request does to its object.
 */
enum ChangeRequestAction: string implements HasLabel
{
    use TranslatesEnum;

    case Create = 'create';
    case Update = 'update';
    case Deactivate = 'deactivate';

    public static function translationKey(): string
    {
        return 'change_request_action';
    }
}
