<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access;

use App\Domain\Access\Permission;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\Access\PermissionResource\Pages;
use App\Filament\Resources\BaseResource;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The permission catalogue is code, not data -- AccessControlSeeder owns it. Shown
 * so an administrator can see what a role actually grants, never edited.
 *
 * @extends BaseResource<Permission>
 */
class PermissionResource extends BaseResource
{
    protected static ?string $model = Permission::class;

    protected static ?string $translationKey = 'permission';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'label_en', 'label_ar'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->getAttribute('name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->defaultGroup('group')
            ->columns([
                TextColumn::make('name')->label(__('fields.code'))->searchable()->sortable()->fontFamily('mono'),

                TextColumn::make('label_en')
                    ->label(__('fields.name'))
                    ->formatStateUsing(fn (Permission $record): string => $record->label()),

                TextColumn::make('group')->label(__('fields.group'))->badge(),

                IconColumn::make('is_sensitive')
                    ->label(__('fields.is_sensitive'))
                    ->boolean(),

                TextColumn::make('roles_count')
                    ->label(__('resources.role.plural_label'))
                    ->counts('roles')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label(__('fields.group'))
                    ->options(fn (): array => Permission::query()
                        ->distinct()
                        ->orderBy('group')
                        ->pluck('group', 'group')
                        ->all()),

                TernaryFilter::make('is_sensitive')->label(__('fields.is_sensitive')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'view' => Pages\ViewPermission::route('/{record}'),
        ];
    }
}
