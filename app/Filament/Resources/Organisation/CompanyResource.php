<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation;

use App\Domain\Organisation\Company;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Organisation\CompanyResource\Pages;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The reporting entity. SHH-01 comes from the approved parameters; it is shown, not
 * edited.
 *
 * @extends BaseResource<Company>
 */
class CompanyResource extends BaseResource
{
    protected static ?string $model = Company::class;

    protected static ?string $translationKey = 'company';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code')),
                TextColumn::make('name')->label(__('fields.name'))
                    ->formatStateUsing(fn (Company $record): string => $record->displayName()),
                TextColumn::make('operating_model')->label(__('fields.operating_model'))->wrap(),
                TextColumn::make('currency')->label(__('fields.currency')),
                TextColumn::make('accounting_start')->label(__('fields.accounting_start'))->date(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
        ];
    }
}
