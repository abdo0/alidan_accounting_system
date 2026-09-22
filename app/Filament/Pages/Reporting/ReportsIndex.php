<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reporting;

use App\Domain\Reporting\ReportDefinition;
use App\Domain\Reporting\ReportRegistry;
use App\Filament\Enums\NavigationGroup;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/** The report catalogue (Document C tab 16), each entry opening in the viewer. */
class ReportsIndex extends Page
{
    protected static ?string $slug = 'reports';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reporting;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports-index';

    public static function getNavigationLabel(): string
    {
        return __('reports.index_title');
    }

    public function getTitle(): string
    {
        return __('reports.index_title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('reports.view') === true;
    }

    /** @return Collection<int, array{definition: ReportDefinition, url: ?string}> */
    public function catalogue(): Collection
    {
        $registry = app(ReportRegistry::class);

        return ReportDefinition::query()->orderBy('code')->get()->map(fn (ReportDefinition $d): array => [
            'definition' => $d,
            'url' => $registry->has($d->code) && auth()->user()?->hasPermission($registry->get($d->code)->permission())
                ? ReportViewer::getUrl(['report' => $d->code])
                : null,
        ]);
    }
}
