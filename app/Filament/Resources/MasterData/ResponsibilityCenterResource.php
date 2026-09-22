<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData;

use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\MasterData\ResponsibilityCenterResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<ResponsibilityCenter> */
class ResponsibilityCenterResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = ResponsibilityCenter::class;

    protected static ?string $translationKey = 'responsibility_center';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('fields.code'))->required()->maxLength(20)->disabledOn('edit'),
            ...static::bilingualNameFields(200),
            Select::make('linked_counterparty_id')->label(__('fields.linked_person'))
                ->relationship('linkedCounterparty', 'name', fn ($query) => $query->where('is_advance_holder', true))
                ->getOptionLabelFromRecordUsing(fn (Counterparty $record): string => $record->label()),
            Toggle::make('is_active')->label(__('fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable(),
                static::nameColumn(),
                TextColumn::make('linkedCounterparty.code')->label(__('fields.linked_person'))->badge(),
                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageResponsibilityCenters::route('/'),
        ];
    }
}
