<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding;

use App\Domain\Funding\Enums\FundingSourceType;
use App\Domain\Funding\FundingSource;
use App\Domain\MasterData\Counterparty;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\Funding\FundingSourceResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<FundingSource> */
class FundingSourceResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = FundingSource::class;

    protected static ?string $translationKey = 'funding_source';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Funding;

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('fields.code'))->required()->maxLength(20)->disabledOn('edit'),
            ...static::bilingualNameFields(200),
            Select::make('source_type')->label(__('fields.source_type'))->options(FundingSourceType::class)->required(),
            Select::make('counterparty_id')->label(__('fields.counterparty'))
                ->relationship('counterparty', 'name')
                ->getOptionLabelFromRecordUsing(fn (Counterparty $record): string => $record->label())
                ->searchable()->preload(),
            Toggle::make('is_active')->label(__('fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable(),
                static::nameColumn(),
                TextColumn::make('source_type')->label(__('fields.source_type'))->badge(),
                TextColumn::make('counterparty.code')->label(__('fields.counterparty'))->badge(),
                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFundingSources::route('/'),
        ];
    }
}
