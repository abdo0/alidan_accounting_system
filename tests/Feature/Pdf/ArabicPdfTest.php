<?php

declare(strict_types=1);

namespace Tests\Feature\Pdf;

use App\Support\Pdf\PdfRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De-risks R-12. If Arabic statement rendering is going to fail on font coverage or
 * shaping, it must fail now and not at the end of the build.
 */
class ArabicPdfTest extends TestCase
{
    /** @return array<string, mixed> */
    private function specimenData(string $locale): array
    {
        $ar = $locale === 'ar';

        return [
            'title' => $ar ? 'ميزان المراجعة' : 'Trial Balance',
            'meta' => $ar ? 'شركة العيدان — كانون الأول ٢٠٢٦' : 'Al-Idan Company — December 2026',
            'headings' => [
                'code' => $ar ? 'رمز الحساب' : 'Code',
                'account' => $ar ? 'اسم الحساب' : 'Account',
                'debit' => $ar ? 'مدين' : 'Debit',
                'credit' => $ar ? 'دائن' : 'Credit',
                'total' => $ar ? 'الإجمالي' : 'Total',
            ],
            'rows' => [
                ['code' => '1100', 'account' => $ar ? 'النقدية في البنك' : 'Cash at bank', 'debit' => '13,100,000', 'credit' => '0'],
                ['code' => '1200', 'account' => $ar ? 'الذمم المدينة' : 'Trade receivables', 'debit' => '4,250,000', 'credit' => '0'],
                ['code' => '4000', 'account' => $ar ? 'المبيعات' : 'Sales', 'debit' => '0', 'credit' => '17,350,000'],
            ],
            'totals' => ['debit' => '17,350,000', 'credit' => '17,350,000'],
        ];
    }

    #[Test]
    public function it_renders_an_arabic_statement(): void
    {
        $pdf = app(PdfRenderer::class)->render('pdf.specimen', $this->specimenData('ar'), 'ar');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3000, strlen($pdf));

        $path = storage_path('app/arabic-statement-specimen.pdf');
        file_put_contents($path, $pdf);

        $text = $this->extractText($path);

        if ($text === null) {
            $this->markTestSkipped('pdftotext not available to verify text extraction.');
        }

        // mPDF shapes Arabic into Presentation Forms-B (U+FE70..U+FEFF) and writes
        // those shaped glyphs into the text layer without a reverse mapping. That is
        // why the raw extraction reads as U+FEE3 U+FEF4 ... rather than م ي ز ا ن.
        // Visually the document is correct -- shaping is exactly what makes Arabic
        // render properly -- but the text layer is not logical Unicode. NFKC folds the
        // presentation forms back to base letters, which is how we assert on content.
        // Consequence to know about: copy-paste and in-reader search of an Arabic PDF
        // will not match text typed normally. See docs note in 02-architecture.
        $normalised = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;

        $this->assertStringContainsString('ميزان', $normalised, 'Arabic title did not survive rendering.');
        $this->assertStringContainsString('المبيعات', $normalised, 'Arabic account name did not survive rendering.');
        $this->assertStringContainsString('17,350,000', $normalised, 'Western digits must remain readable.');

        // Prove the shaping actually happened rather than raw codepoints being dumped:
        // a correctly shaped run contains Presentation Forms-B characters.
        $this->assertMatchesRegularExpression(
            '/[\x{FE70}-\x{FEFF}]/u',
            $text,
            'Arabic was not contextually shaped; the PDF would render as disconnected letters.'
        );
    }

    #[Test]
    public function it_renders_an_english_statement(): void
    {
        $pdf = app(PdfRenderer::class)->render('pdf.specimen', $this->specimenData('en'), 'en');

        $this->assertStringStartsWith('%PDF-', $pdf);

        $path = storage_path('app/english-statement-specimen.pdf');
        file_put_contents($path, $pdf);

        $text = $this->extractText($path);

        if ($text === null) {
            $this->markTestSkipped('pdftotext not available to verify text extraction.');
        }

        $this->assertStringContainsString('Trial Balance', $text);
        $this->assertStringContainsString('Trade receivables', $text);
    }

    private function extractText(string $path): ?string
    {
        exec('command -v pdftotext', $probe, $code);

        if ($code !== 0) {
            return null;
        }

        $out = $path.'.txt';
        exec(sprintf('pdftotext -layout %s %s 2>/dev/null', escapeshellarg($path), escapeshellarg($out)));

        return is_file($out) ? (string) file_get_contents($out) : null;
    }
}
