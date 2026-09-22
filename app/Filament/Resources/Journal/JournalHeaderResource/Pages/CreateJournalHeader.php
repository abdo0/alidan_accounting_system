<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\Pages;

use App\Domain\Ledger\JournalService;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateJournalHeader extends CreateRecord
{
    protected static string $resource = JournalHeaderResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $lines = array_values($data['lines'] ?? []);
        unset($data['lines']);

        $journal = RuleViolationPresenter::attempt(fn () => app(JournalService::class)->saveDraft(auth()->user(), $data, $lines));

        return $journal ?? throw new Halt;
    }

    protected function getRedirectUrl(): string
    {
        return JournalHeaderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
