<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\TransactionType;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Journal\JournalHeaderResource\Pages;
use App\Filament\Resources\Journal\JournalHeaderResource\RelationManagers;
use App\Filament\Support\IqdColumn;
use App\Support\Format\IqdFormatter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Journal entries (M03). Draft is the only editable state; everything after that is
 * a workflow action on the view page (Document B §4.2).
 *
 * @extends BaseResource<JournalHeader>
 */
class JournalHeaderResource extends BaseResource
{
    protected static ?string $model = JournalHeader::class;

    protected static ?string $translationKey = 'journal';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Journal;

    protected static ?int $navigationSort = 10;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['jv_no', 'description_ar', 'source_reference'];
    }

    /**
     * "Own" scope is applied to the query itself, before anything is shown or
     * totalled (Document B §6: security scope before aggregation).
     *
     * @return Builder<JournalHeader>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['transactionType', 'lines']);
        $user = auth()->user();

        if ($user !== null && $user->permissionScope('journal.view')?->value === 'own') {
            $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhereIn('status', JournalStatus::ledgerValues()));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([...JournalForm::header(), JournalForm::lines()])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('pages.journal.header'))->columns(4)->schema([
                TextEntry::make('jv_no')->label(__('fields.jv_no'))->placeholder('—'),
                TextEntry::make('status')->label(__('fields.status'))->badge()->color(fn (JournalHeader $record): string => $record->status->color()),
                TextEntry::make('transactionType.code')->label(__('fields.transaction_type'))
                    ->formatStateUsing(fn (JournalHeader $record): string => $record->transactionType->label()),
                TextEntry::make('postingRule.code')->label(__('fields.posting_rule'))->placeholder('—'),
                TextEntry::make('posting_date')->label(__('fields.posting_date'))->date(),
                TextEntry::make('txn_date')->label(__('fields.txn_date'))->date()->placeholder('—'),
                TextEntry::make('date_status')->label(__('fields.date_status'))->badge(),
                TextEntry::make('period.period_code')->label(__('fields.period')),
                TextEntry::make('description_ar')->label(__('fields.description_ar'))->columnSpan(2),
                TextEntry::make('description_en')->label(__('fields.description_en'))->placeholder('—')->columnSpan(2),
            ]),
            Section::make(__('pages.journal.controls'))->columns(4)->collapsible()->schema([
                TextEntry::make('doc_status')->label(__('fields.doc_status'))->badge(),
                TextEntry::make('doc_ref')->label(__('fields.doc_ref'))->placeholder('—'),
                TextEntry::make('source_reference')->label(__('fields.source_reference'))->placeholder('—'),
                TextEntry::make('approval_ref')->label(__('fields.approval_ref'))->placeholder('—'),
                TextEntry::make('linkedJournal.jv_no')->label(__('fields.linked_journal'))->placeholder('—'),
                TextEntry::make('reversalOf.jv_no')->label(__('fields.reversal_of'))->placeholder('—'),
                TextEntry::make('reversedBy.jv_no')->label(__('fields.reversed_by'))->placeholder('—'),
                TextEntry::make('reversal_reason')->label(__('fields.reversal_reason'))->placeholder('—'),
                TextEntry::make('rejection_comment')->label(__('fields.rejection_comment'))->placeholder('—'),
                TextEntry::make('acknowledged_warnings')->label(__('fields.acknowledged_warnings'))->badge()->placeholder('—'),
            ]),
            Section::make(__('pages.journal.lines'))->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->columns(8)->schema([
                    TextEntry::make('line_no')->label(__('fields.line_no')),
                    TextEntry::make('account.code')->label(__('fields.account'))
                        ->formatStateUsing(fn (JournalLine $record): string => $record->account->label())->columnSpan(2),
                    TextEntry::make('debit')->label(__('fields.debit'))->formatStateUsing(fn ($state): string => IqdFormatter::format($state)),
                    TextEntry::make('credit')->label(__('fields.credit'))->formatStateUsing(fn ($state): string => IqdFormatter::format($state)),
                    TextEntry::make('project.code')->label(__('fields.project')),
                    TextEntry::make('costCenter.code')->label(__('fields.cost_center'))->placeholder('—'),
                    TextEntry::make('counterparty.code')->label(__('fields.counterparty'))->placeholder('—'),
                ]),
            ]),
            Section::make(__('pages.journal.workflow'))->columns(4)->collapsible()->collapsed()->schema([
                TextEntry::make('creator.name')->label(__('fields.created_by')),
                TextEntry::make('reviewer.name')->label(__('fields.reviewed_by'))->placeholder('—'),
                TextEntry::make('approver.name')->label(__('fields.approved_by'))->placeholder('—'),
                TextEntry::make('poster.name')->label(__('fields.posted_by'))->placeholder('—'),
                TextEntry::make('entry_hash')->label(__('fields.entry_hash'))->placeholder('—')->columnSpanFull()->copyable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('jv_no')->label(__('fields.jv_no'))->searchable()->sortable()->placeholder(fn (JournalHeader $record): string => '#'.$record->id),
                TextColumn::make('posting_date')->label(__('fields.posting_date'))->date()->sortable(),
                TextColumn::make('txn_date')->label(__('fields.txn_date'))->date()->placeholder('—')->toggleable(),
                TextColumn::make('transactionType.code')->label(__('fields.transaction_type'))->badge(),
                TextColumn::make('description_ar')->label(__('fields.description_ar'))->searchable()->limit(60)->wrap(),
                IqdColumn::make('amount')->label(__('fields.total_debit'))
                    ->getStateUsing(fn (JournalHeader $record): int => $record->totalDebit()),
                TextColumn::make('status')->label(__('fields.status'))->badge()->color(fn (JournalHeader $record): string => $record->status->color()),
                TextColumn::make('doc_status')->label(__('fields.doc_status'))->badge()->toggleable(),
                TextColumn::make('source_reference')->label(__('fields.source_reference'))->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('fields.status'))->options(JournalStatus::class)->multiple(),
                SelectFilter::make('transaction_type_id')->label(__('fields.transaction_type'))
                    ->options(fn (): array => TransactionType::query()->orderBy('id')->pluck('code', 'id')->all())->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (JournalHeader $record): bool => $record->isEditable()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\ApprovalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalHeaders::route('/'),
            'create' => Pages\CreateJournalHeader::route('/create'),
            'simple' => Pages\CreateSimpleJournal::route('/simple'),
            'view' => Pages\ViewJournalHeader::route('/{record}'),
            'edit' => Pages\EditJournalHeader::route('/{record}/edit'),
        ];
    }
}
