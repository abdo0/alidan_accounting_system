<?php

declare(strict_types=1);

namespace App\Filament\Resources\Setup;

use App\Domain\MasterData\ValueListItem;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\Setup\ValueListItemResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<ValueListItem> */
class ValueListItemResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = ValueListItem::class;

    protected static ?string $translationKey = 'value_list';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 80;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['value', 'value_ar'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('value')->label(__('fields.value'))->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('list_code')->label(__('fields.list_code'))->badge()->sortable(),
                TextColumn::make('code')->label(__('fields.code'))->placeholder('—'),
                TextColumn::make('value')->label(__('fields.value'))->searchable(['value', 'value_ar'])->wrap()
                    ->formatStateUsing(fn (ValueListItem $record): string => app()->getLocale() === 'ar' && $record->value_ar ? $record->value_ar : $record->value),
                TextColumn::make('approval_status')->label(__('fields.approval_status'))->badge(),
                TextColumn::make('source_of_value')->label(__('fields.source_of_value'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageValueListItems::route('/'),
        ];
    }
}
