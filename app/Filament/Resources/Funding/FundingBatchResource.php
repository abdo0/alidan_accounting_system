<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding;

use App\Domain\Funding\Enums\FundingBatchStatus;
use App\Domain\Funding\FundingBatch;
use App\Domain\MasterData\Counterparty;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\Funding\FundingBatchResource\Pages;
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

/** @extends BaseResource<FundingBatch> */
class FundingBatchResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = FundingBatch::class;

    protected static ?string $translationKey = 'funding_batch';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Funding;

    protected static ?int $navigationSort = 80;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['batch_ref'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('batch_ref')->label(__('fields.batch_ref'))->required()->maxLength(60)->disabledOn('edit'),
            TextInput::make('series')->label(__('fields.series'))->maxLength(20),
            Select::make('funding_source_id')->label(__('fields.funding_source'))->relationship('fundingSource', 'code'),
            Select::make('counterparty_id')->label(__('fields.counterparty'))
                ->relationship('counterparty', 'name')
                ->getOptionLabelFromRecordUsing(fn (Counterparty $record): string => $record->label())
                ->searchable()->preload(),
            Select::make('project_id')->label(__('fields.project'))->relationship('project', 'code'),
            DatePicker::make('batch_date')->label(__('fields.batch_date')),
            TextInput::make('source_amount')->label(__('fields.source_amount'))->integer()->minValue(0),
            Textarea::make('actual_payment_source')->label(__('fields.actual_payment_source'))
                ->helperText(__('pages.funding.actual_payment_source_help')),
            Select::make('status')->label(__('fields.status'))->options(FundingBatchStatus::class)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_ref')->label(__('fields.batch_ref'))->searchable()->sortable(),
                TextColumn::make('series')->label(__('fields.series'))->badge(),
                TextColumn::make('fundingSource.code')->label(__('fields.funding_source')),
                TextColumn::make('project.code')->label(__('fields.project'))->badge(),
                TextColumn::make('batch_date')->label(__('fields.batch_date'))->date()->placeholder('—'),
                IqdColumn::make('source_amount')->label(__('fields.source_amount')),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFundingBatches::route('/'),
        ];
    }
}
