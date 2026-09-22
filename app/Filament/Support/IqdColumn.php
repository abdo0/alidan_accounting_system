<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use App\Support\Format\IqdFormatter;
use Filament\Tables\Columns\TextColumn;

/**
 * An IQD figure in a table: right-aligned, no decimals, negatives in parentheses,
 * zero as a dash, in the digits the reader prefers (Document B §10).
 */
final class IqdColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->alignEnd()
            ->formatStateUsing(fn ($state): string => IqdFormatter::format(
                is_scalar($state) ? $state : null,
                self::numerals(),
            ));
    }

    public static function numerals(): string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->numeral_system : 'latn';
    }
}
