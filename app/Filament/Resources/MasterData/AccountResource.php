<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\ChangeRequestService;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Concerns\HasBilingualNameFields;
use App\Filament\Resources\MasterData\AccountResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The approved chart (Document C tab 04). Read-only: a change is proposed here and
 * decided by someone else in the change-request queue (VR-60).
 *
 * @extends BaseResource<Account>
 */
class AccountResource extends BaseResource
{
    use HasBilingualNameFields;

    protected static ?string $model = Account::class;

    protected static ?string $translationKey = 'account';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code')->label(__('fields.code')),
            TextEntry::make('name')->label(__('fields.name')),
            TextEntry::make('name_ar')->label(__('fields.name_ar')),
            TextEntry::make('account_type')->label(__('fields.account_type'))->badge(),
            TextEntry::make('parent.code')->label(__('fields.parent'))->placeholder('—'),
            TextEntry::make('account_level')->label(__('fields.account_level')),
            TextEntry::make('normal_balance')->label(__('fields.normal_balance')),
            TextEntry::make('fs_line_code')->label(__('fields.fs_line'))->placeholder('—'),
            TextEntry::make('control_subledger')->label(__('fields.control_subledger'))->placeholder('—'),
            TextEntry::make('holder.name')->label(__('fields.advance_holder'))->placeholder('—'),
            TextEntry::make('responsibilityCenter.code')->label(__('fields.responsibility_center'))->placeholder('—'),
            IconEntry::make('is_posting')->label(__('fields.is_posting'))->boolean(),
            IconEntry::make('is_active')->label(__('fields.is_active'))->boolean(),
            IconEntry::make('is_cash_account')->label(__('fields.is_cash_account'))->boolean(),
            IconEntry::make('requires_counterparty')->label(__('fields.requires_counterparty'))->boolean(),
            IconEntry::make('requires_advance_holder')->label(__('fields.requires_advance_holder'))->boolean(),
            IconEntry::make('requires_contract')->label(__('fields.requires_contract'))->boolean(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('code')->label(__('fields.code'))->searchable()->sortable()
                    ->weight(fn (Account $record): ?string => $record->is_group ? 'bold' : null),
                TextColumn::make('name')->label(__('fields.name'))->searchable(['name', 'name_ar'])->wrap()
                    ->formatStateUsing(fn (Account $record): string => str_repeat('  ', max(0, $record->account_level - 1)).$record->displayName()),
                TextColumn::make('account_type')->label(__('fields.account_type'))->badge(),
                TextColumn::make('account_level')->label(__('fields.account_level'))->alignCenter(),
                TextColumn::make('normal_balance')->label(__('fields.normal_balance')),
                TextColumn::make('fs_line_code')->label(__('fields.fs_line'))->placeholder('—')->toggleable(),
                IconColumn::make('is_posting')->label(__('fields.is_posting'))->boolean(),
                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),
                TextColumn::make('control_subledger')->label(__('fields.control_subledger'))->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_type')->label(__('fields.account_type'))->options(AccountType::class),
                TernaryFilter::make('is_posting')->label(__('fields.is_posting')),
                TernaryFilter::make('is_active')->label(__('fields.is_active')),
            ])
            ->recordActions([
                ViewAction::make(),
                self::proposeChangeAction(),
            ]);
    }

    public static function proposeChangeAction(): Action
    {
        return Action::make('proposeChange')
            ->label(__('actions.propose_change'))
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (Account $record): bool => ! $record->is_group && (auth()->user()?->can('proposeChange', $record) ?? false))
            ->fillForm(fn (Account $record): array => $record->only(['name', 'name_ar', 'is_active', 'requires_counterparty', 'requires_advance_holder', 'requires_contract']))
            ->schema([
                ...static::bilingualNameFields(200),
                Toggle::make('is_active')->label(__('fields.is_active')),
                Toggle::make('requires_counterparty')->label(__('fields.requires_counterparty')),
                Toggle::make('requires_advance_holder')->label(__('fields.requires_advance_holder')),
                Toggle::make('requires_contract')->label(__('fields.requires_contract')),
                Textarea::make('reason')->label(__('fields.reason'))->required(),
                TextInput::make('approval_ref')->label(__('fields.approval_ref'))->maxLength(120),
            ])
            ->action(function (Account $record, array $data): void {
                $changes = array_filter(
                    collect($data)->only(['name', 'name_ar', 'is_active', 'requires_counterparty', 'requires_advance_holder', 'requires_contract'])->all(),
                    fn ($value, string $key): bool => $record->getAttribute($key) !== $value,
                    ARRAY_FILTER_USE_BOTH,
                );

                if ($changes === []) {
                    return;
                }

                try {
                    app(ChangeRequestService::class)->propose(auth()->user(), 'account', $record, $changes, $data['reason'], $data['approval_ref'] ?? null);
                    Notification::make()->success()->title(__('pages.change_request.proposed'))->send();
                } catch (RuleViolation $violation) {
                    Notification::make()->danger()->title($violation->getMessage())->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
        ];
    }
}
