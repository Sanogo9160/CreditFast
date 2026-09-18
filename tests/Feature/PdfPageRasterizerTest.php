<?php

namespace Tests\Feature;

use App\Services\Ocr\PdfPageRasterizer;
use Tests\TestCase;

class PdfPageRasterizerTest extends TestCase
{
    public function test_returns_no_pages_when_ghostscript_is_not_configured(): void
    {
        config([
            'ocr.ghostscript.binary' => 'C:\\creditfast-missing-gswin64c.exe',
        ]);

        $rasterizer = app(PdfPageRasterizer::class);
        $pdf = $this->minimalPdfPath();

        $pages = $rasterizer->rasterize($pdf, 1);

        $this->assertSame([], $pages);
        @unlink($pdf);
    }

    public function test_rasterizes_a_pdf_when_ghostscript_is_available(): void
    {
        $binary = config('ocr.ghostscript.binary');
        $detected = is_string($binary) && $binary !== '' && is_file($binary);
        if (! $detected) {
            $matches = glob('C:\\Program Files\\gs\\*\\bin\\gswin64c.exe') ?: [];
            $detected = $matches !== [];
            if ($detected) {
                config(['ocr.ghostscript.binary' => $matches[0]]);
            }
        }

        if (! $detected) {
            $this->markTestSkipped('Ghostscript n’est pas installé sur cette machine.');
        }

        $rasterizer = app(PdfPageRasterizer::class);
        $pdf = $this->minimalPdfPath();
        $pages = $rasterizer->rasterize($pdf, 1);

        $this->assertNotSame([], $pages);
        $this->assertFileExists($pages[0]);

        foreach ($pages as $page) {
            @unlink($page);
        }
        @unlink($pdf);
    }

    private function minimalPdfPath(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'creditfast-ocr-test-'.uniqid('', true).'.pdf';
        $pdf = "%PDF-1.1\n1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj\n4 0 obj<< /Length 44 >>stream\nBT /F1 12 Tf 20 150 Td (Salaire FCFA) Tj ET\nendstream\nendobj\n5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\nxref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000274 00000 n \n0000000368 00000 n \ntrailer<< /Size 6 /Root 1 0 R >>\nstartxref\n455\n%%EOF\n";
        file_put_contents($path, $pdf);

        return $path;
    }
}
