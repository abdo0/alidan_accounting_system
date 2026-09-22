<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation;

use App\Domain\Organisation\FiscalYear;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Organisation\FiscalYearResource\Pages;
use App\Filament\Resources\Organisation\FiscalYearResource\RelationManagers\PeriodsRelationManager;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The fiscal calendar and its periods (M15). Periods are opened by the calendar and
 * moved through Soft Close, Final Close and Locked by the closing actions.
 *
 * @extends BaseResource<FiscalYear>
 */
class FiscalYearResource extends BaseResource
{
    protected static ?string $model = FiscalYear::class;

    protected static ?string $translationKey = 'fiscal_year';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Closing;

    protected static ?int $navigationSort = 10;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['year_code'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('year_code')->label(__('fields.year_code')),
            TextEntry::make('starts_on')->label(__('fields.starts_on'))->date(),
            TextEntry::make('ends_on')->label(__('fields.ends_on'))->date(),
            TextEntry::make('status')->label(__('fields.status'))->badge(),
        ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year_code')
            ->columns([
                TextColumn::make('year_code')->label(__('fields.year_code'))->sortable(),
                TextColumn::make('starts_on')->label(__('fields.starts_on'))->date(),
                TextColumn::make('ends_on')->label(__('fields.ends_on'))->date(),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PeriodsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalYears::route('/'),
            'view' => Pages\ViewFiscalYear::route('/{record}'),
        ];
    }
}
