<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\Pages;

use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\Posting\ReclassificationService;
use App\Domain\Ledger\Posting\ReversalService;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\ValidationChain;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Ledger\Workflow\JournalWorkflow;
use App\Domain\MasterData\Account;
use App\Filament\Resources\Journal\JournalForm;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\RuleViolationPresenter;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Every workflow step is an action here. Each is offered only in the state and to
 * the permission that allows it, and each is re-checked by the domain service --
 * the button's visibility is a convenience, never the control (Document B §8).
 */
class ViewJournalHeader extends ViewRecord
{
    protected static string $resource = JournalHeaderResource::class;

    /** Everything the page shows, loaded once: lazy loading is an error here. */
    protected function resolveRecord(int|string $key): Model
    {
        return JournalHeader::query()
            ->with(self::RELATIONS)
            ->findOrFail($key);
    }

    public const RELATIONS = [
        'lines.account', 'lines.project', 'lines.costCenter', 'lines.counterparty', 'lines.cashAccount',
        'lines.counterparty.shareholder', 'transactionType', 'postingRule', 'period', 'linkedJournal',
        'reversalOf', 'reversedBy', 'creator', 'reviewer', 'approver', 'poster',
    ];

    private function journal(): JournalHeader
    {
        /** @var JournalHeader $record */
        $record = $this->getRecord();

        return $record;
    }

    private function can(string $permission): bool
    {
        return (bool) auth()->user()?->hasPermission($permission);
    }

    /** @param  callable(): mixed  $step */
    private function run(callable $step, string $message): void
    {
        $result = RuleViolationPresenter::attempt($step);

        if ($result !== null) {
            Notification::make()->success()->title($message)->send();
            $this->refreshFormData(['status']);
            $this->redirect(JournalHeaderResource::getUrl('view', ['record' => $result instanceof JournalHeader ? $result : $this->journal()]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => $this->journal()->isEditable()),

            Action::make('submit')
                ->label(__('actions.submit'))
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => $this->journal()->status === JournalStatus::Draft && $this->can('journal.submit'))
                ->schema(fn (): array => $this->warningAcknowledgement())
                ->action(fn (array $data) => $this->run(
                    fn () => app(JournalWorkflow::class)->submit(auth()->user(), $this->journal(), array_values($data['acknowledged'] ?? [])),
                    __('pages.journal.submitted'),
                )),

            Action::make('review')
                ->label(__('actions.review'))
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->visible(fn (): bool => $this->journal()->status === JournalStatus::Submitted && $this->can('journal.review'))
                ->schema([Textarea::make('comment')->label(__('fields.comment'))])
                ->action(fn (array $data) => $this->run(
                    fn () => app(JournalWorkflow::class)->review(auth()->user(), $this->journal(), $data['comment'] ?? null),
                    __('pages.journal.reviewed'),
                )),

            Action::make('approve')
                ->label(__('actions.approve'))
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->visible(fn (): bool => in_array($this->journal()->status, [JournalStatus::Submitted, JournalStatus::Reviewed], true) && $this->can('journal.approve'))
                ->fillForm(fn (): array => ['approval_ref' => $this->journal()->approval_ref])
                ->schema([
                    TextInput::make('approval_ref')->label(__('fields.approval_ref'))->maxLength(120),
                    Textarea::make('comment')->label(__('fields.comment')),
                ])
                ->action(fn (array $data) => $this->run(
                    fn () => app(JournalWorkflow::class)->approve(auth()->user(), $this->journal(), $data['approval_ref'] ?? null, $data['comment'] ?? null),
                    __('pages.journal.approved'),
                )),

            Action::make('reject')
                ->label(__('actions.reject'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => $this->journal()->status->isRejectable() && ($this->can('journal.review') || $this->can('journal.approve')))
                ->schema([Textarea::make('comment')->label(__('fields.rejection_comment'))->required()])
                ->action(fn (array $data) => $this->run(
                    fn () => app(JournalWorkflow::class)->reject(auth()->user(), $this->journal(), $data['comment']),
                    __('pages.journal.rejected'),
                )),

            Action::make('post')
                ->label(__('actions.post'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->journal()->status === JournalStatus::Approved && $this->can('journal.post'))
                ->action(function (): void {
                    $posted = RuleViolationPresenter::attempt(fn () => app(JournalWorkflow::class)->post(auth()->user(), $this->journal()));

                    if ($posted instanceof JournalHeader) {
                        Notification::make()->success()->title(__('pages.journal.posted', ['jv' => $posted->jv_no]))->send();
                        $this->redirect(JournalHeaderResource::getUrl('view', ['record' => $posted]));
                    }
                }),

            Action::make('reverse')
                ->label(__('actions.reverse'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->visible(fn (): bool => $this->journal()->status === JournalStatus::Posted && $this->can('journal.reverse'))
                ->schema([
                    Textarea::make('reason')->label(__('fields.reversal_reason'))->required(),
                    DatePicker::make('posting_date')->label(__('fields.posting_date'))->default(now())->required(),
                ])
                ->action(function (array $data): void {
                    $mirror = RuleViolationPresenter::attempt(fn () => app(ReversalService::class)->reverse(
                        auth()->user(),
                        $this->journal(),
                        $data['reason'],
                        CarbonImmutable::parse($data['posting_date']),
                    ));

                    if ($mirror instanceof JournalHeader) {
                        Notification::make()->success()->title(__('pages.journal.reversed', ['jv' => $mirror->jv_no]))->send();
                        $this->redirect(JournalHeaderResource::getUrl('view', ['record' => $mirror]));
                    }
                }),

            Action::make('reclassify')
                ->label(__('actions.reclassify'))
                ->icon('heroicon-o-arrows-right-left')
                ->visible(fn (): bool => $this->journal()->status === JournalStatus::Posted && $this->can('journal.create'))
                ->schema([
                    Select::make('line_id')->label(__('fields.line_no'))
                        ->options(fn (): array => $this->journal()->lines->filter(fn (JournalLine $l): bool => $l->debit > 0)
                            ->mapWithKeys(fn (JournalLine $l): array => [$l->id => $l->line_no.' — '.$l->account->label()])->all())
                        ->required(),
                    Select::make('account_id')->label(__('fields.account'))->options(fn (): array => JournalForm::postableAccounts())->searchable()->required(),
                    TextInput::make('approval_ref')->label(__('fields.approval_ref'))->required(),
                ])
                ->action(function (array $data): void {
                    $draft = RuleViolationPresenter::attempt(fn () => app(ReclassificationService::class)->prepare(
                        auth()->user(),
                        JournalLine::query()->findOrFail($data['line_id']),
                        Account::query()->findOrFail($data['account_id']),
                        $data['approval_ref'],
                    ));

                    if ($draft instanceof JournalHeader) {
                        $this->redirect(JournalHeaderResource::getUrl('view', ['record' => $draft]));
                    }
                }),
        ];
    }

    /**
     * Warning rules (VR-13, VR-33, VR-45) need an explicit acknowledgement before
     * the entry is submitted.
     *
     * @return array<int, CheckboxList>
     */
    private function warningAcknowledgement(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $warnings = app(ValidationChain::class)->run($this->journal(), $user, Checkpoint::Submit)->warnings();

        if ($warnings === []) {
            return [];
        }

        return [
            CheckboxList::make('acknowledged')
                ->label(__('pages.journal.acknowledge'))
                ->options(collect($warnings)->mapWithKeys(fn (Violation $v): array => [$v->rule => $v->rule.' — '.$v->message])->all()),
        ];
    }
}
