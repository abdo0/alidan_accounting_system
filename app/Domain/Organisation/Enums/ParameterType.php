<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * How a parameter value, always stored as text, is read back.
 */
enum ParameterType: string implements HasLabel
{
    use TranslatesEnum;

    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case Boolean = 'boolean';

    public static function translationKey(): string
    {
        return 'parameter_type';
    }
}
