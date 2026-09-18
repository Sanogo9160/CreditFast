<?php

namespace App\Services\Ocr;

use Throwable;

class CompositeDocumentTextExtractor implements DocumentTextExtractor
{
    public function __construct(
        protected PdfTextReader $pdfTextReader,
        protected TesseractReader $tesseractReader,
        protected PdfPageRasterizer $pdfPageRasterizer,
    ) {}

    public function extract(string $absolutePath, string $mimeType): array
    {
        $mime = strtolower($mimeType);

        if (str_contains($mime, 'pdf') || str_ends_with(strtolower($absolutePath), '.pdf')) {
            return $this->extractFromPdf($absolutePath);
        }

        if (str_starts_with($mime, 'image/') || $this->isImagePath($absolutePath)) {
            return $this->extractFromImage($absolutePath);
        }

        return $this->failure('Type de fichier non pris en charge pour l’OCR (PDF, JPG, PNG).');
    }

    /**
     * @return array{text: string, confidence: float, engine: string, source: string, error: ?string}
     */
    protected function extractFromPdf(string $absolutePath): array
    {
        $embeddedText = $this->pdfTextReader->read($absolutePath);
        $minLength = (int) config('ocr.pdf_min_text_length', 80);

        if (mb_strlen($embeddedText) >= $minLength) {
            return [
                'text' => $embeddedText,
                'confidence' => 88.0,
                'engine' => 'pdf_parser',
                'source' => 'embedded_pdf_text',
                'error' => null,
            ];
        }

        $pages = $this->pdfPageRasterizer->rasterize(
            $absolutePath,
            (int) config('ocr.max_pdf_pages', 3)
        );

        if ($pages === []) {
            if ($embeddedText !== '') {
                return [
                    'text' => $embeddedText,
                    'confidence' => 45.0,
                    'engine' => 'pdf_parser',
                    'source' => 'embedded_pdf_text_short',
                    'error' => 'PDF peu textuel : Imagick/Ghostscript requis pour OCR des scans.',
                ];
            }

            return $this->failure('PDF image (scan) : Imagick et Ghostscript sont requis, ou téléversez un JPG/PNG.');
        }

        try {
            $chunks = [];
            foreach ($pages as $page) {
                $chunks[] = $this->tesseractReader->read($page);
            }

            $text = trim(implode("\n\n", array_filter($chunks)));

            return [
                'text' => $text,
                'confidence' => $text === '' ? 0.0 : 78.0,
                'engine' => 'tesseract',
                'source' => 'pdf_raster_ocr',
                'error' => $text === '' ? 'Tesseract n’a extrait aucun texte du scan PDF.' : null,
            ];
        } catch (Throwable $exception) {
            return $this->failure('Échec OCR Tesseract sur PDF : '.$exception->getMessage());
        } finally {
            foreach ($pages as $page) {
                @unlink($page);
            }
        }
    }

    /**
     * @return array{text: string, confidence: float, engine: string, source: string, error: ?string}
     */
    protected function extractFromImage(string $absolutePath): array
    {
        try {
            $text = $this->tesseractReader->read($absolutePath);

            return [
                'text' => $text,
                'confidence' => $text === '' ? 0.0 : 82.0,
                'engine' => 'tesseract',
                'source' => 'image_ocr',
                'error' => $text === '' ? 'Tesseract n’a extrait aucun texte de l’image.' : null,
            ];
        } catch (Throwable $exception) {
            return $this->failure('Échec Tesseract : '.$exception->getMessage().'. Vérifiez que Tesseract OCR est installé.');
        }
    }

    /**
     * @return array{text: string, confidence: float, engine: string, source: string, error: ?string}
     */
    protected function failure(string $message): array
    {
        return [
            'text' => '',
            'confidence' => 0.0,
            'engine' => 'none',
            'source' => 'failed',
            'error' => $message,
        ];
    }

    protected function isImagePath(string $path): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|tif{1,2})$/i', $path);
    }
}
