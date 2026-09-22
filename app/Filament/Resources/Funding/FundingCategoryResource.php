<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding;

use App\Domain\Funding\FundingCategory;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\Funding\FundingCategoryResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<FundingCategory> */
class FundingCategoryResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = FundingCategory::class;

    protected static ?string $translationKey = 'funding_category';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Funding;

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('fields.code'))->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable(),
                static::nameColumn(),
                TextColumn::make('approval_status')->label(__('fields.approval_status'))->badge(),
                TextColumn::make('source_of_value')->label(__('fields.source_of_value'))->toggleable(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFundingCategories::route('/'),
        ];
    }
}
