<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

/**
 * Every master record in the UAS carries an English and an Arabic name. The Arabic
 * input is forced to RTL regardless of the panel's direction, so an Arabic name
 * entered while the UI is in English still reads correctly.
 */
trait HasBilingualNameFields
{
    /** @return array<int, TextInput> */
    protected static function bilingualNameFields(int $maxLength = 150): array
    {
        return [
            TextInput::make('name')
                ->label(__('fields.name'))
                ->required()
                ->maxLength($maxLength),

            TextInput::make('name_ar')
                ->label(__('fields.name_ar'))
                ->maxLength($maxLength)
                ->extraInputAttributes(['dir' => 'rtl']),
        ];
    }

    /**
     * One column, not two: the name in the reader's language, with the other
     * available to search. Two name columns crowd out the figures.
     */
    protected static function nameColumn(string $attribute = 'name'): TextColumn
    {
        return TextColumn::make($attribute)
            ->label(__('fields.name'))
            ->searchable(['name', 'name_ar'])
            ->sortable()
            ->formatStateUsing(fn ($record): string => method_exists($record, 'displayName')
                ? $record->displayName()
                : (string) $record->getAttribute('name'));
    }
}
