<?php

return [
    'languages' => explode('+', (string) env('OCR_LANGUAGES', 'fra+eng')),
    'timeout' => (int) env('OCR_TIMEOUT', 60),
    'pdf_min_text_length' => (int) env('OCR_PDF_MIN_TEXT_LENGTH', 80),
    'max_pdf_pages' => (int) env('OCR_MAX_PDF_PAGES', 3),
    'pdf_density' => (int) env('OCR_PDF_DENSITY', 200),
    'tesseract' => [
        'binary' => env('TESSERACT_BINARY'),
    ],
    'ghostscript' => [
        'binary' => env('GHOSTSCRIPT_BINARY'),
    ],
    'imagick' => [
        'configure_path' => env('IMAGICK_CONFIGURE_PATH'),
    ],
];
