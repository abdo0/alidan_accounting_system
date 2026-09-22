<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\Pages;

use App\Filament\Resources\Journal\JournalHeaderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJournalHeaders extends ListRecords
{
    protected static string $resource = JournalHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simple')
                ->label(__('actions.new_simple'))
                ->icon('heroicon-o-arrows-right-left')
                ->url(JournalHeaderResource::getUrl('simple'))
                ->visible(fn (): bool => JournalHeaderResource::canCreate()),
            CreateAction::make(),
        ];
    }
}
