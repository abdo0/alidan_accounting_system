<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Export;

use App\Domain\Organisation\Company;
use App\Domain\Reporting\Result\ReportResult;
use App\Support\Pdf\PdfRenderer;

/**
 * PDF export and print of a report result: the same rows and controls as the
 * screen, the filter set printed in the header, Arabic right-to-left.
 */
final class PdfExporter
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    public function export(ReportResult $result, string $user, string $numerals = 'latn'): string
    {
        return $this->renderer->render('reports.pdf', [
            'result' => $result,
            'company' => Company::current(),
            'user' => $user,
            'numerals' => $numerals,
        ], orientation: count($result->columns) > 6 ? 'L' : 'P');
    }
}
