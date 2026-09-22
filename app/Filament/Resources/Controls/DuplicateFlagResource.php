<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls;

use App\Domain\Controls\DuplicateFlag;
use App\Domain\Controls\Duplicates\DispositionService;
use App\Domain\Controls\Enums\DuplicateDisposition;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Controls\DuplicateFlagResource\Pages;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\IqdColumn;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The duplicate register (RPT-22 as a working list). Flags are dispositioned, never
 * deleted (Document B §2.5).
 *
 * @extends BaseResource<DuplicateFlag>
 */
class DuplicateFlagResource extends BaseResource
{
    protected static ?string $model = DuplicateFlag::class;

    protected static ?string $translationKey = 'duplicate_flag';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Controls;

    protected static ?int $navigationSort = 20;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['match_reason'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('journal.jv_no')->label(__('fields.jv_no'))
                    ->placeholder(fn (DuplicateFlag $record): string => '#'.$record->journal_header_id)
                    ->url(fn (DuplicateFlag $record): string => JournalHeaderResource::getUrl('view', ['record' => $record->journal_header_id])),
                TextColumn::make('matchedJournal.jv_no')->label(__('fields.matched_journal'))->placeholder('—'),
                TextColumn::make('flag_type')->label(__('fields.flag_type'))->badge(),
                TextColumn::make('match_reason')->label(__('fields.match_reason'))->wrap(),
                TextColumn::make('score')->label(__('fields.score')),
                IqdColumn::make('value_at_risk')->label(__('fields.value_at_risk')),
                TextColumn::make('disposition')->label(__('fields.disposition'))->badge()->placeholder(__('pages.duplicate.undecided')),
                TextColumn::make('dispositioner.name')->label(__('fields.dispositioned_by'))->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('undecided')
                    ->label(__('pages.duplicate.undecided'))
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('disposition'),
                        false: fn (Builder $q) => $q->whereNotNull('disposition'),
                    ),
                SelectFilter::make('flag_type')->label(__('fields.flag_type'))->options(DuplicateFlagType::class),
            ])
            ->recordActions([
                Action::make('disposition')
                    ->label(__('actions.disposition'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('duplicates.disposition'))
                    ->schema([
                        Select::make('disposition')->label(__('fields.disposition'))->options(DuplicateDisposition::class)->required(),
                        Textarea::make('note')->label(__('fields.disposition_note'))->required(),
                    ])
                    ->action(fn (DuplicateFlag $record, array $data) => RuleViolationPresenter::attempt(fn () => app(DispositionService::class)->disposition(
                        auth()->user(),
                        $record,
                        DuplicateDisposition::from($data['disposition']),
                        $data['note'],
                    ))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDuplicateFlags::route('/'),
        ];
    }
}
