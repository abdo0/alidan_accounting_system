<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cash;

use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Counterparty;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Cash\BankAccountResource\Pages;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<BankAccount> */
class BankAccountResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = BankAccount::class;

    protected static ?string $translationKey = 'bank_account';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Cash;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('fields.code'))->disabled(),
            ...static::bilingualNameFields(200),
            Select::make('custodian_id')->label(__('fields.custodian'))
                ->relationship('custodian', 'name')
                ->getOptionLabelFromRecordUsing(fn (Counterparty $record): string => $record->label())
                ->searchable()->preload(),
            Select::make('responsible_user_id')->label(__('fields.responsible_accountant'))
                ->relationship('responsibleUser', 'name')->searchable()->preload(),
            TextInput::make('bank_name')->label(__('fields.bank_name'))->maxLength(200),
            TextInput::make('account_number')->label(__('fields.account_number'))->maxLength(60),
            Toggle::make('is_active')->label(__('fields.is_active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable(),
                static::nameColumn(),
                TextColumn::make('ba_type')->label(__('fields.ba_type'))->badge(),
                TextColumn::make('project.code')->label(__('fields.project'))->badge(),
                TextColumn::make('custodian.name')->label(__('fields.custodian'))->placeholder('—'),
                TextColumn::make('responsibleUser.name')->label(__('fields.responsible_accountant'))->placeholder('—'),
                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBankAccounts::route('/'),
        ];
    }
}
