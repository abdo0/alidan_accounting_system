<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access;

use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\Access\UserResource\Pages;
use App\Filament\Resources\BaseResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * Users are deactivated, never deleted: their id is the actor on every audit row and
 * the created_by of every entry they touched.
 *
 * @extends BaseResource<User>
 */
class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $translationKey = 'user';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pages.user.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('fields.name'))->required()->maxLength(150),

                    TextInput::make('name_ar')
                        ->label(__('fields.name_ar'))
                        ->maxLength(150)
                        ->extraInputAttributes(['dir' => 'rtl']),

                    TextInput::make('username')
                        ->label(__('fields.username'))
                        ->maxLength(60)
                        ->unique(ignoreRecord: true),

                    TextInput::make('email')
                        ->label(__('fields.email'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('job_title')->label(__('fields.job_title'))->maxLength(120),

                    TextInput::make('password')
                        ->label(__('fields.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (?User $record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText(__('pages.user.password_help')),

                    Select::make('locale')
                        ->label(__('fields.locale'))
                        ->options(config('app.available_locales'))
                        ->default(config('app.locale'))
                        ->required(),

                    Select::make('numeral_system')
                        ->label(__('fields.numeral_system'))
                        ->options(['latn' => '0123456789', 'arab' => '٠١٢٣٤٥٦٧٨٩'])
                        ->default('latn')
                        ->required(),
                ]),

            Section::make(__('pages.user.access'))
                ->columns(2)
                ->schema([
                    Select::make('roles')
                        ->label(__('fields.roles'))
                        ->relationship('roles', 'label_en')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->label())
                        ->multiple()
                        ->preload(),

                    Toggle::make('is_active')->label(__('fields.is_active'))->default(true),

                    Toggle::make('is_service_account')
                        ->label(__('fields.is_service_account'))
                        ->helperText(__('pages.user.service_account_help')),
                ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(['name', 'name_ar'])
                    ->sortable()
                    ->formatStateUsing(fn (User $record): string => $record->getFilamentName()),

                TextColumn::make('email')->label(__('fields.email'))->searchable()->copyable(),

                TextColumn::make('roles.name')
                    ->label(__('fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn ($state, $record): string => (string) $state),

                IconColumn::make('mfa_confirmed_at')
                    ->label(__('fields.mfa_confirmed_at'))
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->mfa_confirmed_at !== null),

                IconColumn::make('is_service_account')->label(__('fields.is_service_account'))->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label(__('fields.is_active'))->boolean(),

                TextColumn::make('last_login_at')->label(__('fields.last_login_at'))->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('fields.is_active'))->default(true),
                SelectFilter::make('roles')->label(__('fields.roles'))->relationship('roles', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('unlock')
                    ->label(__('actions.unlock'))
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (User $record): bool => $record->isLocked() && (bool) auth()->user()?->hasPermission('users.manage'))
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['locked_until' => null, 'failed_attempts' => 0])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
