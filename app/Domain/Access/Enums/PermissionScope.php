<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document B §8: scope is evaluated server-side.
 */
enum PermissionScope: string implements HasLabel
{
    use TranslatesEnum;

    case All = 'all';
    case Own = 'own';
    case Conditional = 'conditional';

    public static function translationKey(): string
    {
        return 'permission_scope';
    }
}
