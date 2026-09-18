<?php

namespace Tests\Support;

use App\Services\Ocr\DocumentTextExtractor;

class FakeDocumentTextExtractor implements DocumentTextExtractor
{
    /**
     * @param  array{text: string, confidence: float, engine: string, source: string, error: ?string}  $result
     */
    public function __construct(private array $result) {}

    public function extract(string $absolutePath, string $mimeType): array
    {
        return $this->result;
    }
}
