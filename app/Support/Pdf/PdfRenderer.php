<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;

/**
 * Renders a Blade view to PDF, RTL-aware.
 *
 * Filament's automatic RTL does not extend here: mPDF needs its own directionality
 * and an Arabic-capable font selected explicitly. mPDF bundles XB Riyaz, which shapes
 * Arabic correctly (contextual forms and ligatures), so no external font is required.
 */
final class PdfRenderer
{
    private const ARABIC_FONT = 'xbriyaz';

    private const LATIN_FONT = 'dejavusans';

    /** @param  array<string, mixed>  $data */
    public function render(string $view, array $data = [], ?string $locale = null, string $orientation = 'P'): string
    {
        $locale ??= app()->getLocale();
        $isRtl = $locale === 'ar';

        $html = view($view, [...$data, 'locale' => $locale, 'isRtl' => $isRtl])->render();

        return $this->renderHtml($html, $isRtl, $orientation);
    }

    /** @throws MpdfException */
    public function renderHtml(string $html, bool $isRtl = false, string $orientation = 'P'): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4',
            'orientation' => $orientation,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'tempDir' => storage_path('app/mpdf-temp'),
            'default_font' => $isRtl ? self::ARABIC_FONT : self::LATIN_FONT,
            'default_font_size' => 9,
            // autoScriptToLang/autoLangToFont let a mixed-script document (an Arabic
            // statement with Latin account codes) pick the right font per run.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        if ($isRtl) {
            $mpdf->SetDirectionality('rtl');
        }

        $mpdf->SetCreator('SHH-01 Financial & Accounting System');
        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', Destination::STRING_RETURN);
    }
}
