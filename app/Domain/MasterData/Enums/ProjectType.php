<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document C tab 05 "Type".
 */
enum ProjectType: string implements HasLabel
{
    use TranslatesEnum;

    case Project = 'project';
    case Corporate = 'corporate';

    public static function translationKey(): string
    {
        return 'project_type';
    }
}
