<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Controls\Exceptions\ExceptionLifecycle;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Controls\ControlExceptionResource\Pages;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\IqdColumn;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The exceptions register (M09, RPT-24's log). Exceptions are raised by the engine
 * and the migration, never typed in; they are resolved, never deleted (VR-59).
 *
 * @extends BaseResource<ControlException>
 */
class ControlExceptionResource extends BaseResource
{
    protected static ?string $model = ControlException::class;

    protected static ?string $translationKey = 'exception';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Controls;

    protected static ?int $navigationSort = 10;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['exception_no', 'source_code', 'subject'];
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ControlException::query()->where('status', '!=', ExceptionStatus::Resolved)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('exception_no')->label(__('fields.exception_no')),
            TextEntry::make('source_code')->label(__('fields.source_code'))->placeholder('—'),
            TextEntry::make('status')->label(__('fields.status'))->badge(),
            TextEntry::make('category')->label(__('fields.category'))->badge(),
            TextEntry::make('raised_date')->label(__('fields.raised_date'))->date(),
            TextEntry::make('owner.name')->label(__('fields.owner'))->placeholder('—'),
            TextEntry::make('subject')->label(__('fields.subject'))->columnSpanFull(),
            TextEntry::make('description')->label(__('fields.description'))->placeholder('—')->columnSpanFull(),
            TextEntry::make('amount')->label(__('fields.amount'))->placeholder('—'),
            TextEntry::make('volume')->label(__('fields.volume'))->placeholder('—'),
            TextEntry::make('journal.jv_no')->label(__('fields.jv_no'))->placeholder('—')
                ->url(fn (ControlException $record): ?string => $record->journal_header_id ? JournalHeaderResource::getUrl('view', ['record' => $record->journal_header_id]) : null),
            TextEntry::make('required_action')->label(__('fields.required_action'))->placeholder('—')->columnSpanFull(),
            TextEntry::make('proposed_resolution')->label(__('fields.proposed_resolution'))->placeholder('—')->columnSpanFull(),
            TextEntry::make('resolution')->label(__('fields.resolution'))->placeholder('—')->columnSpanFull(),
            TextEntry::make('resolver.name')->label(__('fields.resolved_by'))->placeholder('—'),
            RepeatableEntry::make('comments')->label(__('fields.comments'))->columnSpanFull()->schema([
                TextEntry::make('user.name')->hiddenLabel(),
                TextEntry::make('comment')->hiddenLabel(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $lifecycle = fn (): ExceptionLifecycle => app(ExceptionLifecycle::class);

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('exception_no')->label(__('fields.exception_no'))->searchable()->sortable(),
                TextColumn::make('category')->label(__('fields.category'))->badge(),
                TextColumn::make('subject')->label(__('fields.subject'))->searchable()->wrap()->limit(80),
                IqdColumn::make('amount')->label(__('fields.amount')),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('owner.name')->label(__('fields.owner'))->placeholder('—'),
                TextColumn::make('raised_date')->label(__('fields.raised_date'))->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('fields.status'))->options(ExceptionStatus::class)->default(ExceptionStatus::Open->value),
                SelectFilter::make('category')->label(__('fields.category'))->options(ExceptionCategory::class)->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('take')
                    ->label(__('actions.take'))
                    ->visible(fn (ControlException $record): bool => $record->status === ExceptionStatus::Open && (bool) auth()->user()?->hasPermission('exceptions.propose'))
                    ->action(fn (ControlException $record) => RuleViolationPresenter::attempt(fn () => $lifecycle()->take(auth()->user(), $record))),
                Action::make('propose')
                    ->label(__('actions.propose_resolution'))
                    ->visible(fn (ControlException $record): bool => $record->isOpen() && (bool) auth()->user()?->hasPermission('exceptions.propose'))
                    ->schema([Textarea::make('proposal')->label(__('fields.proposed_resolution'))->required()])
                    ->action(fn (ControlException $record, array $data) => RuleViolationPresenter::attempt(fn () => $lifecycle()->propose(auth()->user(), $record, $data['proposal']))),
                Action::make('resolve')
                    ->label(__('actions.resolve'))
                    ->color('success')
                    ->visible(fn (ControlException $record): bool => $record->isOpen() && (bool) auth()->user()?->hasPermission('exceptions.resolve'))
                    ->schema([Textarea::make('resolution')->label(__('fields.resolution'))->required()])
                    ->action(function (ControlException $record, array $data) use ($lifecycle): void {
                        if (RuleViolationPresenter::attempt(fn () => $lifecycle()->resolve(auth()->user(), $record, $data['resolution'])) !== null) {
                            Notification::make()->success()->title(__('pages.exception.resolved'))->send();
                        }
                    }),
                Action::make('comment')
                    ->label(__('actions.comment'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('exceptions.comment'))
                    ->schema([Textarea::make('comment')->label(__('fields.comment'))->required()])
                    ->action(fn (ControlException $record, array $data) => RuleViolationPresenter::attempt(fn () => $lifecycle()->comment(auth()->user(), $record, $data['comment']))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListControlExceptions::route('/'),
            'view' => Pages\ViewControlException::route('/{record}'),
        ];
    }
}
