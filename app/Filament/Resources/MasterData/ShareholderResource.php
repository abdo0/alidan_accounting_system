<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData;

use App\Domain\MasterData\Shareholder;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\MasterData\ShareholderResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<Shareholder> */
class ShareholderResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = Shareholder::class;

    protected static ?string $translationKey = 'shareholder';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 50;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code'];
    }

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
                TextColumn::make('code')->label(__('fields.code'))->sortable(),
                TextColumn::make('counterparty.name')->label(__('fields.name'))
                    ->formatStateUsing(fn (Shareholder $record): string => $record->counterparty->displayName()),
                TextColumn::make('loanAccount.code')->label(__('fields.loan_account')),
                TextColumn::make('currentAccount.code')->label(__('fields.current_account')),
                TextColumn::make('capitalAccount.code')->label(__('fields.capital_account')),
                IconColumn::make('is_approved_financier')->label(__('fields.is_approved_financier'))->boolean(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShareholders::route('/'),
        ];
    }
}
