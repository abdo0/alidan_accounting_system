<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation;

use App\Domain\Closing\ChecklistTask;
use App\Domain\Closing\Enums\ChecklistStatus;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Organisation\ChecklistTaskResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The monthly closing checklist (Document A §13, RPT-28 as a working list).
 *
 * @extends BaseResource<ChecklistTask>
 */
class ChecklistTaskResource extends BaseResource
{
    protected static ?string $model = ChecklistTask::class;

    protected static ?string $translationKey = 'checklist_task';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Closing;

    protected static ?int $navigationSort = 20;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['period', 'responsible', 'completer']))
            ->defaultSort('task_no')
            ->columns([
                TextColumn::make('period.period_code')->label(__('fields.period')),
                TextColumn::make('task_no')->label('#'),
                TextColumn::make('name')->label(__('fields.name'))->wrap()->formatStateUsing(fn (ChecklistTask $r): string => $r->displayName()),
                TextColumn::make('responsible.name')->label(__('fields.responsible_accountant'))->placeholder('—'),
                TextColumn::make('due_date')->label(__('fields.due_date'))->date(),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('completer.name')->label(__('fields.completed_by'))->placeholder('—'),
                TextColumn::make('comments')->label(__('fields.comment'))->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('period_id')->label(__('fields.period'))
                    ->options(fn (): array => AccountingPeriod::query()->orderBy('starts_on')->pluck('period_code', 'id')->all())
                    ->default(fn (): ?int => AccountingPeriod::containing(Company::current()->id, now())?->id),
                SelectFilter::make('status')->label(__('fields.status'))->options(ChecklistStatus::class),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label(__('closing.actions.complete'))
                    ->visible(fn (ChecklistTask $r): bool => $r->status === ChecklistStatus::Pending && (bool) auth()->user()?->hasPermission('closing.manage'))
                    ->schema([
                        Select::make('status')->label(__('fields.status'))->options([ChecklistStatus::Completed->value => ChecklistStatus::Completed->getLabel(), ChecklistStatus::Exception->value => ChecklistStatus::Exception->getLabel()])->default(ChecklistStatus::Completed->value)->required(),
                        Textarea::make('comments')->label(__('fields.comment')),
                    ])
                    ->action(fn (ChecklistTask $r, array $data) => $r->forceFill([
                        'status' => $data['status'], 'completed_by' => auth()->id(), 'completed_at' => now(), 'comments' => $data['comments'] ?? null,
                    ])->save()),
                Action::make('assign')
                    ->label(__('actions.assign'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('closing.manage'))
                    ->schema([Select::make('responsible_id')->label(__('fields.responsible_accountant'))->options(fn (): array => User::query()->where('is_active', true)->pluck('name', 'id')->all())->required()])
                    ->action(fn (ChecklistTask $r, array $data) => $r->forceFill(['responsible_id' => $data['responsible_id']])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListChecklistTasks::route('/')];
    }
}
