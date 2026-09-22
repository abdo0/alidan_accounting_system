<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reporting;

use App\Domain\Reporting\Contracts\Report;
use App\Domain\Reporting\Export\ExcelExporter;
use App\Domain\Reporting\Export\PdfExporter;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Shared\Audit\AuditRecorder;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\IqdColumn;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs any report of the catalogue. The filters live in the URL, so every
 * drill-down link carries its parent's filter set into the child report
 * (UAT-046, UAT-047), and the Excel, PDF and print outputs render exactly the
 * result on screen with that filter set printed in the header.
 */
class ReportViewer extends Page
{
    protected static ?string $slug = 'reports/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-viewer';

    #[Url]
    public ?string $report = null;

    /** @var array<string, mixed> */
    #[Url]
    public array $filters = [];

    private ?ReportResult $result = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('reports.view') === true
            || auth()->user()?->hasPermission('audit.view') === true;
    }

    public function mount(): void
    {
        abort_unless($this->report !== null && app(ReportRegistry::class)->has($this->report), 404);
        abort_unless(auth()->user()?->hasPermission($this->definition()->permission()) === true, 403);

        $this->getSchema('form')?->fill($this->filters);
    }

    public function getTitle(): string
    {
        return $this->result()->title;
    }

    public function definition(): Report
    {
        return app(ReportRegistry::class)->get((string) $this->report);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->columns(4)->components(ReportFilterForm::fields($this->definition()->filters()));
    }

    public function applyFilters(): void
    {
        $this->filters = $this->getSchema('form')?->getState() ?? $this->filters;
        $this->result = null;
    }

    public function result(): ReportResult
    {
        return $this->result ??= $this->definition()->run(FilterSet::fromArray($this->filters), auth()->user());
    }

    /** @param  array<string, mixed>|null  $target */
    public function drillUrl(?array $target): ?string
    {
        return match (true) {
            $target === null => null,
            isset($target['journal']) => JournalHeaderResource::getUrl('view', ['record' => $target['journal']]),
            isset($target['report']) => self::getUrl(['report' => $target['report'], 'filters' => $target['filters'] ?? []]),
            default => null,
        };
    }

    public function numerals(): string
    {
        return IqdColumn::numerals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')->label(__('reports.run'))->icon('heroicon-o-play')->action(fn () => $this->applyFilters()),
            Action::make('excel')
                ->label(__('reports.export_excel'))
                ->icon('heroicon-o-table-cells')
                ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('reports.export'))
                ->action(fn (): StreamedResponse => $this->download('xlsx')),
            Action::make('pdf')
                ->label(__('reports.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('reports.export'))
                ->action(fn (): StreamedResponse => $this->download('pdf')),
        ];
    }

    private function download(string $format): StreamedResponse
    {
        $result = $this->result();
        $user = (string) auth()->user()?->name;
        $name = $result->code.'-'.now()->format('Ymd-His').'.'.$format;

        app(AuditRecorder::class)->event('report_export', 'report', null, $result->code.' '.$format, ['filters' => $this->filters]);

        return response()->streamDownload(function () use ($result, $format, $user): void {
            if ($format === 'pdf') {
                echo app(PdfExporter::class)->export($result, $user, $this->numerals());

                return;
            }

            $path = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
            app(ExcelExporter::class)->export($result, $path, $user);
            readfile($path);
            @unlink($path);
        }, $name);
    }
}
