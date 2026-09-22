<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\Pages;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\JournalService;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Draft only: the service refuses anything else (VR-18). */
class EditJournalHeader extends EditRecord
{
    protected static string $resource = JournalHeaderResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return JournalHeader::query()->with(['lines'])->findOrFail($key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var JournalHeader $record */
        $record = $this->getRecord();

        $data['lines'] = $record->lines->map(fn (JournalLine $line): array => $line->only([
            'account_id', 'debit', 'credit', ...JournalLine::DIMENSIONS, 'advance_id', 'settlement_deadline', 'notes',
        ]))->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = array_values($data['lines'] ?? []);
        unset($data['lines']);

        /** @var JournalHeader $record */
        $journal = RuleViolationPresenter::attempt(fn () => app(JournalService::class)->saveDraft(auth()->user(), $data, $lines, $record));

        return $journal ?? throw new Halt;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->label(__('actions.delete_draft'))
                ->using(fn (JournalHeader $record) => RuleViolationPresenter::attempt(fn () => app(JournalService::class)->deleteDraft(auth()->user(), $record))),
        ];
    }
}
