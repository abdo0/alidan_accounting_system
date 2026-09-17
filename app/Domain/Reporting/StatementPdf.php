<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Support\Pdf\PdfRenderer;

/**
 * Renders a resolved statement to PDF in the standard's column frame.
 *
 * Amounts print with thousands separators and no decimals: IQD has no minor unit in
 * practice, and the statements are headed دينــار.
 */
final class StatementPdf
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    public function render(RenderedStatement $statement, string $entityName, ?string $locale = null): string
    {
        return $this->pdf->render('pdf.statement', [
            'statement' => $statement,
            'entityName' => $entityName,
            'format' => fn (?string $amount): string => $amount === null
                ? ''
                : number_format((float) $amount, 0, '.', ','),
        ], $locale);
    }
}
