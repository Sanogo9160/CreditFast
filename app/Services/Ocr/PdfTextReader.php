<?php

namespace App\Services\Ocr;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class PdfTextReader
{
    public function read(string $absolutePath): string
    {
        try {
            $pdf = (new PdfParser)->parseFile($absolutePath);

            return trim($pdf->getText() ?? '');
        } catch (Throwable) {
            return '';
        }
    }
}
