<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The approval history (Document B §4.2): one row per transition, permanent. */
class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pages.journal.approvals');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('action')->label(__('fields.action'))->badge(),
                TextColumn::make('from_status')->label(__('fields.from_status'))->placeholder('—'),
                TextColumn::make('to_status')->label(__('fields.to_status'))->placeholder('—'),
                TextColumn::make('user.name')->label(__('fields.user'))->placeholder('—'),
                TextColumn::make('comment')->label(__('fields.comment'))->placeholder('—')->wrap(),
                TextColumn::make('approval_ref')->label(__('fields.approval_ref'))->placeholder('—'),
                TextColumn::make('action_at')->label(__('fields.action_at'))->dateTime(),
            ]);
    }
}
