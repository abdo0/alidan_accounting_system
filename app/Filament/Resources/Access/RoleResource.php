<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\Access\RoleResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Roles are where segregation of duties is expressed. is_system roles are the
 * design; they can be relabelled but not deleted.
 *
 * @extends BaseResource<Role>
 */
class RoleResource extends BaseResource
{
    protected static ?string $model = Role::class;

    protected static ?string $translationKey = 'role';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'label_en', 'label_ar'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.role.label'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('fields.code'))
                        ->required()
                        ->maxLength(60)
                        ->disabled(fn (?Role $record): bool => $record?->is_system === true),

                    TextInput::make('label_en')->label(__('fields.label_en'))->required()->maxLength(120),

                    TextInput::make('label_ar')
                        ->label(__('fields.label_ar'))
                        ->maxLength(120)
                        ->extraInputAttributes(['dir' => 'rtl']),

                    Textarea::make('description')->label(__('fields.description'))->rows(2),

                    Toggle::make('is_read_only')->label(__('fields.is_read_only')),
                    Toggle::make('requires_mfa')->label(__('fields.requires_mfa')),
                ]),

            Section::make(__('fields.permissions'))
                ->schema([
                    CheckboxList::make('permissions')
                        ->label(__('fields.permissions'))
                        ->relationship('permissions', 'name')
                        ->getOptionLabelFromRecordUsing(fn (Permission $r): string => $r->label().' ('.$r->name.')')
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('fields.code'))->searchable()->sortable(),

                TextColumn::make('label_en')
                    ->label(__('fields.name'))
                    ->formatStateUsing(fn (Role $record): string => $record->label()),

                TextColumn::make('permissions_count')
                    ->label(__('fields.permissions'))
                    ->counts('permissions')
                    ->badge(),

                TextColumn::make('users_count')
                    ->label(__('resources.user.plural_label'))
                    ->counts('users')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_read_only')->label(__('fields.is_read_only'))->boolean(),
                IconColumn::make('requires_mfa')->label(__('fields.requires_mfa'))->boolean(),
                IconColumn::make('is_system')->label(__('fields.is_system'))->boolean(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
