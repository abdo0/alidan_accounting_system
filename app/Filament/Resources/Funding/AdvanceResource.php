<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding;

use App\Domain\Advances\Advance;
use App\Domain\Advances\AdvanceClaimService;
use App\Domain\Advances\AdvancePosition;
use App\Domain\Advances\AdvanceSettlement;
use App\Domain\Advances\Enums\EvidenceStatus;
use App\Domain\Advances\Enums\SettlementClassification;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Funding\AdvanceResource\Pages;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\IqdColumn;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The advance register (M04). Advances are opened by postings, never typed in; the
 * figures shown are computed from the ledger each time (Document B §4.3).
 *
 * @extends BaseResource<Advance>
 */
class AdvanceResource extends BaseResource
{
    protected static ?string $model = Advance::class;

    protected static ?string $translationKey = 'advance';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Funding;

    protected static ?int $navigationSort = 10;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['advance_ref', 'purpose'];
    }

    public static function table(Table $table): Table
    {
        $position = fn (Advance $record): AdvancePosition => once(fn (): AdvancePosition => AdvancePosition::of($record));

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['holder', 'account']))
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('advance_ref')->label(__('fields.advance_ref'))->searchable()
                    ->url(fn (Advance $record): ?string => $record->issue_journal_id ? JournalHeaderResource::getUrl('view', ['record' => $record->issue_journal_id]) : null),
                TextColumn::make('holder.name')->label(__('fields.advance_holder'))
                    ->formatStateUsing(fn (Advance $record): string => $record->holder->displayName()),
                TextColumn::make('account.code')->label(__('fields.account')),
                TextColumn::make('issue_date')->label(__('fields.issue_date'))->date()->sortable(),
                TextColumn::make('settlement_deadline')->label(__('fields.settlement_deadline'))->date()->placeholder('—'),
                IqdColumn::make('issued')->label(__('reports.columns.issued'))->getStateUsing(fn (Advance $r): int => $position($r)->issued),
                IqdColumn::make('outstanding')->label(__('reports.columns.outstanding'))->getStateUsing(fn (Advance $r): int => $position($r)->outstanding),
                IqdColumn::make('pending')->label(__('reports.columns.claimed'))->getStateUsing(fn (Advance $r): int => $position($r)->pendingClaims),
                TextColumn::make('status')->label(__('fields.status'))->badge()->getStateUsing(fn (Advance $r): string => $position($r)->status()->getLabel()),
            ])
            ->recordActions([
                Action::make('claim')
                    ->label(__('actions.record_claim'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('journal.review'))
                    ->schema([
                        TextInput::make('amount')->label(__('fields.amount'))->integer()->minValue(1)->required(),
                        Textarea::make('notes')->label(__('fields.notes'))->required(),
                    ])
                    ->action(fn (Advance $record, array $data) => RuleViolationPresenter::attempt(fn () => app(AdvanceClaimService::class)->recordPendingClaim(auth()->user(), $record, (int) $data['amount'], $data['notes']))),
                Action::make('classify')
                    ->label(__('actions.classify'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('journal.review'))
                    ->schema(fn (Advance $record): array => [
                        Select::make('settlement_id')->label(__('fields.settlement'))->required()
                            ->options($record->settlements()->get()->mapWithKeys(fn (AdvanceSettlement $s): array => [$s->id => '#'.$s->id.' — '.$s->settlement_type->getLabel()])->all()),
                        Select::make('classification')->label(__('fields.classification'))->options(SettlementClassification::class)->required(),
                        Select::make('evidence_status')->label(__('fields.evidence_status'))->options(EvidenceStatus::class),
                    ])
                    ->action(fn (array $data) => RuleViolationPresenter::attempt(fn () => app(AdvanceClaimService::class)->classify(
                        auth()->user(),
                        AdvanceSettlement::query()->findOrFail($data['settlement_id']),
                        SettlementClassification::from($data['classification']),
                        isset($data['evidence_status']) ? EvidenceStatus::from($data['evidence_status']) : null,
                    ))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdvances::route('/')];
    }
}
