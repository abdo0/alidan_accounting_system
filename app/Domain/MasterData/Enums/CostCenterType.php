<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document C tab 06 "Type". A cost centre is functional, never a vendor.
 */
enum CostCenterType: string implements HasLabel
{
    use TranslatesEnum;

    case Project = 'project';
    case Sga = 'sga';
    case Operations = 'operations';

    public static function translationKey(): string
    {
        return 'cost_center_type';
    }
}
