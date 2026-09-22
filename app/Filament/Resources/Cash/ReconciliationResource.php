<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cash;

use App\Domain\Cash\BookBalance;
use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Enums\ReconciliationType;
use App\Domain\Cash\Reconciliation;
use App\Domain\Cash\ReconciliationService;
use App\Domain\MasterData\BankAccount;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Cash\ReconciliationResource\Pages;
use App\Filament\Support\IqdColumn;
use App\Filament\Support\RuleViolationPresenter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use UnitEnum;

/**
 * Cash counts and bank reconciliations (M11, VR-58). The actual balance is the only
 * figure entered; the book balance and variance are computed each time.
 *
 * @extends BaseResource<Reconciliation>
 */
class ReconciliationResource extends BaseResource
{
    protected static ?string $model = Reconciliation::class;

    protected static ?string $translationKey = 'reconciliation';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Cash;

    protected static ?int $navigationSort = 20;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['notes'];
    }

    public static function table(Table $table): Table
    {
        $book = fn (Reconciliation $r): int => app(BookBalance::class)->at($r->bankAccount, $r->as_at_date);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['bankAccount', 'responsible', 'preparer']))
            ->defaultSort('as_at_date', 'desc')
            ->columns([
                TextColumn::make('bankAccount.code')->label(__('fields.cash_account')),
                TextColumn::make('recon_type')->label(__('fields.type'))->badge(),
                TextColumn::make('as_at_date')->label(__('fields.as_at'))->date(),
                IqdColumn::make('book')->label(__('reports.columns.book_balance'))->getStateUsing($book),
                IqdColumn::make('actual_balance')->label(__('reports.columns.actual_balance')),
                IqdColumn::make('variance')->label(__('reports.columns.variance'))->getStateUsing(fn (Reconciliation $r): int => $r->actual_balance - $book($r)),
                TextColumn::make('status')->label(__('fields.status'))->badge(),
                TextColumn::make('responsible.name')->label(__('fields.responsible_accountant')),
            ])
            ->headerActions([
                Action::make('prepare')
                    ->label(__('actions.prepare_reconciliation'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('cash.reconcile_prepare'))
                    ->schema([
                        Select::make('bank_account_id')->label(__('fields.cash_account'))->options(fn (): array => BankAccount::query()->orderBy('code')->pluck('code', 'id')->all())->required(),
                        Select::make('recon_type')->label(__('fields.type'))->options([ReconciliationType::Bank->value => ReconciliationType::Bank->getLabel(), ReconciliationType::CashCount->value => ReconciliationType::CashCount->getLabel()])->required(),
                        DatePicker::make('as_at_date')->label(__('fields.as_at'))->required(),
                        TextInput::make('actual_balance')->label(__('reports.columns.actual_balance'))->integer()->required(),
                        Select::make('responsible_user_id')->label(__('fields.responsible_accountant'))->options(fn (): array => User::query()->where('is_active', true)->pluck('name', 'id')->all())->required(),
                        FileUpload::make('evidence')->label(__('fields.file'))->storeFiles(false)->required(),
                        Textarea::make('notes')->label(__('fields.notes')),
                    ])
                    ->action(function (array $data): void {
                        $file = $data['evidence'];

                        RuleViolationPresenter::attempt(fn () => app(ReconciliationService::class)->prepare(
                            auth()->user(),
                            BankAccount::query()->findOrFail($data['bank_account_id']),
                            CarbonImmutable::parse($data['as_at_date']),
                            (int) $data['actual_balance'],
                            $file instanceof UploadedFile ? $file : null,
                            User::query()->find($data['responsible_user_id']),
                            ReconciliationType::from($data['recon_type']),
                            $data['notes'] ?? null,
                        ));
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('actions.approve'))
                    ->color('success')
                    ->visible(fn (Reconciliation $r): bool => $r->status === ReconciliationStatus::Submitted && (bool) auth()->user()?->hasPermission('cash.reconcile_approve'))
                    ->requiresConfirmation()
                    ->action(fn (Reconciliation $r) => RuleViolationPresenter::attempt(fn () => app(ReconciliationService::class)->approve(auth()->user(), $r))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReconciliations::route('/')];
    }
}
