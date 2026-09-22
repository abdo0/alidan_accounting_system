<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Dashboard\Kpis;
use App\Domain\Reporting\Filters\FilterSet;
use App\Filament\Pages\Reporting\ReportViewer;
use App\Filament\Support\IqdColumn;
use App\Support\Format\IqdFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** RPT-27: the executive dashboard. Every figure opens the report behind it. */
class ExecutiveKpis extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('dashboard.view') === true;
    }

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $numerals = IqdColumn::numerals();

        return array_map(fn (array $kpi): Stat => Stat::make(
            __('dashboard.kpis.'.$kpi['key']),
            $kpi['amount'] ? IqdFormatter::format($kpi['value'], $numerals) : (string) $kpi['value'],
        )->url(ReportViewer::getUrl(['report' => $kpi['report'], 'filters' => $kpi['filters']]))
            ->color($kpi['key'] === 'ledger_difference' ? ($kpi['value'] === 0 ? 'success' : 'danger') : null),
            app(Kpis::class)->all(FilterSet::fromArray([])));
    }
}
