<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation\FiscalYearResource\RelationManagers;

use App\Domain\Closing\PeriodCloseService;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Enums\PeriodStatus;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The periods of a year, with the closing actions each role may take (M15). */
class PeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources.accounting_period.plural_label');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** @param  list<PeriodStatus>  $from */
    private static function step(string $name, string $permission, array $from, callable $run, bool $reason = false): Action
    {
        return Action::make($name)
            ->label(__('closing.actions.'.$name))
            ->visible(fn (AccountingPeriod $record): bool => in_array($record->status, $from, true) && (bool) auth()->user()?->hasPermission($permission))
            ->requiresConfirmation()
            ->schema($reason ? [Textarea::make('reason')->label(__('fields.reason'))->required()] : [])
            ->action(function (AccountingPeriod $record, array $data) use ($run): void {
                if (RuleViolationPresenter::attempt(fn () => $run(app(PeriodCloseService::class), $record, $data)) !== null) {
                    Notification::make()->success()->title(__('closing.done'))->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->paginated(false)
            ->columns([
                TextColumn::make('period_code')->label(__('fields.period_code')),
                TextColumn::make('starts_on')->label(__('fields.starts_on'))->date(),
                TextColumn::make('ends_on')->label(__('fields.ends_on'))->date(),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('reopen_reason')->label(__('fields.reopen_reason'))->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                self::step('soft_close', 'period.soft_close', [PeriodStatus::Open], fn (PeriodCloseService $s, AccountingPeriod $p) => $s->softClose(auth()->user(), $p)),
                self::step('final_close', 'period.final_close', [PeriodStatus::Open, PeriodStatus::SoftClose], fn (PeriodCloseService $s, AccountingPeriod $p) => $s->finalClose(auth()->user(), $p)),
                self::step('reopen', 'period.reopen', [PeriodStatus::SoftClose, PeriodStatus::FinalClose], fn (PeriodCloseService $s, AccountingPeriod $p, array $d) => $s->reopen(auth()->user(), $p, $d['reason']), reason: true),
                self::step('lock', 'period.lock', [PeriodStatus::FinalClose], fn (PeriodCloseService $s, AccountingPeriod $p) => $s->lock(auth()->user(), $p)),
                Action::make('blockers')
                    ->label(__('closing.actions.blockers'))
                    ->icon('heroicon-o-question-mark-circle')
                    ->visible(fn (AccountingPeriod $record): bool => in_array($record->status, [PeriodStatus::Open, PeriodStatus::SoftClose], true))
                    ->action(function (AccountingPeriod $record): void {
                        $blockers = app(PeriodCloseService::class)->blockers($record);
                        Notification::make()->title($blockers === [] ? __('closing.ready') : __('closing.actions.blockers'))
                            ->body(implode("\n", array_map(fn (string $b): string => '• '.$b, $blockers)))
                            ->{$blockers === [] ? 'success' : 'warning'}()->persistent()->send();
                    }),
            ]);
    }
}
