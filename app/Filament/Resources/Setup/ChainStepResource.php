<?php

declare(strict_types=1);

namespace App\Filament\Resources\Setup;

use App\Domain\Funding\ChainStep;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\Setup\ChainStepResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** @extends BaseResource<ChainStep> */
class ChainStepResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = ChainStep::class;

    protected static ?string $translationKey = 'chain_step';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 90;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'label', 'label_ar'];
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
                TextColumn::make('label')->label(__('fields.name'))->wrap()
                    ->formatStateUsing(fn (ChainStep $record): string => app()->getLocale() === 'ar' && $record->label_ar ? $record->label_ar : $record->label),
                TextColumn::make('debit_selector')->label(__('fields.debit_selector'))->wrap(),
                TextColumn::make('credit_selector')->label(__('fields.credit_selector'))->wrap(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageChainSteps::route('/'),
        ];
    }
}
