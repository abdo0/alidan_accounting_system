<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData;

use App\Domain\MasterData\Contract;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Enums\ContractStatus;
use App\Domain\MasterData\Enums\ContractType;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\MasterData\ContractResource\Pages;
use App\Filament\Support\IqdColumn;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<Contract> */
class ContractResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = Contract::class;

    protected static ?string $translationKey = 'contract';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 60;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['contract_no', 'title', 'title_ar'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('contract_no')->label(__('fields.contract_no'))->required()->maxLength(40)->disabledOn('edit'),
            TextInput::make('title')->label(__('fields.title'))->required()->maxLength(300),
            TextInput::make('title_ar')->label(__('fields.title_ar'))->maxLength(300)->extraInputAttributes(['dir' => 'rtl']),
            Select::make('contract_type')->label(__('fields.contract_type'))->options(ContractType::class)->required(),
            Select::make('counterparty_id')->label(__('fields.counterparty'))
                ->relationship('counterparty', 'name')
                ->getOptionLabelFromRecordUsing(fn (Counterparty $record): string => $record->label())
                ->searchable()->preload(),
            Select::make('project_id')->label(__('fields.project'))->relationship('project', 'code'),
            TextInput::make('contract_value')->label(__('fields.contract_value'))->integer()->minValue(0),
            DatePicker::make('signed_on')->label(__('fields.signed_on')),
            DatePicker::make('starts_on')->label(__('fields.starts_on')),
            DatePicker::make('ends_on')->label(__('fields.ends_on')),
            TextInput::make('retention_pct')->label(__('fields.retention_pct'))->numeric()->minValue(0)->maxValue(100),
            TextInput::make('advance_recovery_pct')->label(__('fields.advance_recovery_pct'))->numeric()->minValue(0)->maxValue(100),
            Select::make('status')->label(__('fields.status'))->options(ContractStatus::class)->required(),
            Textarea::make('notes')->label(__('fields.notes')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_no')->label(__('fields.contract_no'))->searchable()->sortable(),
                TextColumn::make('title')->label(__('fields.title'))->searchable(['title', 'title_ar'])
                    ->formatStateUsing(fn (Contract $record): string => app()->getLocale() === 'ar' && $record->title_ar ? $record->title_ar : $record->title),
                TextColumn::make('counterparty.code')->label(__('fields.counterparty'))->badge(),
                IqdColumn::make('contract_value')->label(__('fields.contract_value')),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('pending_fields')->label(__('fields.pending_fields'))->badge()->toggleable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContracts::route('/'),
        ];
    }
}
