<?php

namespace App\Services\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractReader
{
    public function read(string $imagePath): string
    {
        $ocr = new TesseractOCR($imagePath);

        $languages = array_values(array_filter(config('ocr.languages', ['fra', 'eng'])));
        if ($languages !== []) {
            $ocr->lang(...$languages);
        }

        $binary = config('ocr.tesseract.binary');
        if (is_string($binary) && $binary !== '') {
            $ocr->executable($binary);
        }

        return trim($ocr->run((int) config('ocr.timeout', 60)));
    }
}
