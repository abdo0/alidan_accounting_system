<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\RelationManagers;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Shared\Document;
use App\Domain\Shared\Documents\DocumentService;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Evidence for the entry (M16). Uploading is allowed at any status -- a document
 * arriving after posting is exactly what the Missing and Partial statuses wait for
 * -- but nothing uploaded can be edited or removed.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pages.journal.documents');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('doc_type')->label(__('fields.doc_type'))->badge(),
                TextColumn::make('doc_ref')->label(__('fields.doc_ref'))->placeholder('—'),
                TextColumn::make('doc_date')->label(__('fields.doc_date'))->date()->placeholder('—'),
                TextColumn::make('original_name')->label(__('fields.original_name')),
                TextColumn::make('sha256')->label(__('fields.sha256'))->limit(12)->copyable()->toggleable(),
                TextColumn::make('uploader.name')->label(__('fields.uploaded_by')),
                TextColumn::make('uploaded_at')->label(__('fields.uploaded_at'))->dateTime(),
            ])
            ->headerActions([
                Action::make('attach')
                    ->label(__('pages.journal.upload'))
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('journal.create'))
                    ->schema([
                        Select::make('doc_type')->label(__('fields.doc_type'))
                            ->options(collect(Document::TYPES)->mapWithKeys(fn (string $t): array => [$t => __('enums.doc_type.'.$t)])->all())
                            ->required(),
                        TextInput::make('doc_ref')->label(__('fields.doc_ref'))->maxLength(120),
                        DatePicker::make('doc_date')->label(__('fields.doc_date')),
                        FileUpload::make('file')->label(__('fields.file'))->storeFiles(false)->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var JournalHeader $journal */
                        $journal = $this->getOwnerRecord();
                        $file = $data['file'];

                        if ($file instanceof TemporaryUploadedFile || $file instanceof UploadedFile) {
                            RuleViolationPresenter::attempt(fn () => app(DocumentService::class)->attach(
                                auth()->user(),
                                $journal,
                                $file,
                                $data['doc_type'],
                                $data['doc_ref'] ?? null,
                                $data['doc_date'] ?? null,
                            ));
                        }
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('documents.view'))
                    ->action(fn (Document $record) => Storage::disk($record->disk)->download($record->object_key, $record->original_name)),
            ]);
    }
}
