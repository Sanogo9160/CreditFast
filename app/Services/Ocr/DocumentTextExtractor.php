<?php

namespace App\Services\Ocr;

interface DocumentTextExtractor
{
    /**
     * @return array{
     *     text: string,
     *     confidence: float,
     *     engine: string,
     *     source: string,
     *     error: ?string
     * }
     */
    public function extract(string $absolutePath, string $mimeType): array;
}
