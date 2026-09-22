<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls;

use App\Domain\MasterData\ChangeRequest;
use App\Domain\MasterData\ChangeRequestService;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Controls\ChangeRequestResource\Pages;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The maker-checker queue for chart and master-data changes (VR-60).
 *
 * @extends BaseResource<ChangeRequest>
 */
class ChangeRequestResource extends BaseResource
{
    protected static ?string $model = ChangeRequest::class;

    protected static ?string $translationKey = 'change_request';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Controls;

    protected static ?int $navigationSort = 90;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['object_type', 'reason'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('object_type')->label(__('fields.object_type'))->badge(),
                TextColumn::make('object_id')->label(__('fields.object_id')),
                TextColumn::make('action')->label(__('fields.action'))->badge(),
                TextColumn::make('previous')->label(__('fields.old_value'))->wrap()
                    ->getStateUsing(fn (ChangeRequest $record): string => self::describe($record->previous)),
                TextColumn::make('payload')->label(__('fields.new_value'))->wrap()
                    ->getStateUsing(fn (ChangeRequest $record): string => self::describe($record->payload)),
                TextColumn::make('reason')->label(__('fields.reason'))->wrap()->limit(120),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('proposer.name')->label(__('fields.proposed_by'))->placeholder(__('pages.change_request.system')),
                TextColumn::make('decider.name')->label(__('fields.decided_by'))->placeholder('—'),
                TextColumn::make('approval_ref')->label(__('fields.approval_ref'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('fields.status'))->options(ChangeRequestStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('actions.approve'))
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ChangeRequest $record): bool => auth()->user()?->can('decide', $record) ?? false)
                    ->schema([
                        TextInput::make('approval_ref')->label(__('fields.approval_ref'))->maxLength(120),
                        Textarea::make('note')->label(__('fields.decision_note')),
                    ])
                    ->action(fn (ChangeRequest $record, array $data) => self::run(
                        fn () => app(ChangeRequestService::class)->approve(auth()->user(), $record, $data['note'] ?? null, $data['approval_ref'] ?? null),
                    )),
                Action::make('reject')
                    ->label(__('actions.reject'))
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (ChangeRequest $record): bool => auth()->user()?->can('decide', $record) ?? false)
                    ->schema([Textarea::make('note')->label(__('fields.decision_note'))->required()])
                    ->action(fn (ChangeRequest $record, array $data) => self::run(
                        fn () => app(ChangeRequestService::class)->reject(auth()->user(), $record, $data['note']),
                    )),
            ]);
    }

    /** @param  array<string, mixed>|null  $values */
    private static function describe(?array $values): string
    {
        return collect($values ?? [])
            ->map(fn ($value, string $key): string => $key.': '.(is_bool($value) ? ($value ? '✓' : '✗') : (string) $value))
            ->implode(' · ');
    }

    private static function run(callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title(__('pages.change_request.decided'))->send();
        } catch (RuleViolation $violation) {
            Notification::make()->danger()->title($violation->getMessage())->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeRequests::route('/'),
        ];
    }
}
