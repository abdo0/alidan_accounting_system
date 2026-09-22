<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData;

use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Enums\CounterpartyType;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\MasterData\CounterpartyResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<Counterparty> */
class CounterpartyResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = Counterparty::class;

    protected static ?string $translationKey = 'counterparty';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('fields.code'))->required()->maxLength(20)->disabledOn('edit'),
            ...static::bilingualNameFields(300),
            Select::make('cp_type')->label(__('fields.cp_type'))->options(CounterpartyType::class)->required(),
            TextInput::make('role_description')->label(__('fields.role_description'))->maxLength(200),
            Toggle::make('is_contractor')->label(__('fields.is_contractor')),
            Toggle::make('is_supplier')->label(__('fields.is_supplier')),
            Toggle::make('is_advance_holder')->label(__('fields.is_advance_holder')),
            Toggle::make('is_employee')->label(__('fields.is_employee')),
            Toggle::make('is_government')->label(__('fields.is_government')),
            Textarea::make('source_alias')->label(__('fields.source_alias'))->disabled(),
            Textarea::make('notes')->label(__('fields.notes')),
            Toggle::make('is_active')->label(__('fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable(),
                static::nameColumn(),
                TextColumn::make('cp_type')->label(__('fields.cp_type'))->badge(),
                TextColumn::make('role_description')->label(__('fields.role_description'))->toggleable(),
                TextColumn::make('aliases.alias')->label(__('fields.aliases'))->listWithLineBreaks()->toggleable(),
                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCounterparties::route('/'),
        ];
    }
}
