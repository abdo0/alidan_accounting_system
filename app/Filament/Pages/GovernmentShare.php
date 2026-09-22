<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Project;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Revenue\GovernmentShareService;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * M14: compute the government share for a period and project, and prepare the
 * recognition entry (TT-28) as a draft for review and approval. The rate shown is
 * the one the parameters table holds for the period end (VR-43).
 */
class GovernmentShare extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Funding;

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.government-share';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('pages.government_share.title');
    }

    public function getTitle(): string
    {
        return __('pages.government_share.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('reports.view') === true;
    }

    public function mount(): void
    {
        $this->getSchema('form')?->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(3)->components([
            Select::make('period_id')->label(__('fields.period'))->options(fn (): array => AccountingPeriod::query()->orderBy('starts_on')->pluck('period_code', 'id')->all())->searchable()->live(),
            Select::make('project_id')->label(__('fields.project'))->options(fn (): array => Project::query()->pluck('code', 'id')->all())->live(),
            Select::make('cost_center_id')->label(__('fields.cost_center'))->options(fn (): array => CostCenter::query()->pluck('code', 'id')->all()),
        ]);
    }

    /** @return array{eligible: int, excluded: int, rate: string, due: int, recognised: int, to_recognise: int}|null */
    public function figures(): ?array
    {
        $period = AccountingPeriod::query()->find($this->data['period_id'] ?? null);

        return $period === null ? null : RuleViolationPresenter::attempt(fn () => app(GovernmentShareService::class)->compute($period, isset($this->data['project_id']) ? (int) $this->data['project_id'] : null));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prepare')
                ->label(__('pages.government_share.prepare'))
                ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('journal.create'))
                ->action(function (): void {
                    $period = AccountingPeriod::query()->find($this->data['period_id'] ?? null);

                    if ($period === null || empty($this->data['project_id']) || empty($this->data['cost_center_id'])) {
                        return;
                    }

                    $draft = RuleViolationPresenter::attempt(fn () => app(GovernmentShareService::class)->prepareRecognition(
                        auth()->user(), $period, (int) $this->data['project_id'], (int) $this->data['cost_center_id'],
                    ));

                    if ($draft !== null) {
                        $this->redirect(JournalHeaderResource::getUrl('view', ['record' => $draft]));
                    }
                }),
        ];
    }
}
