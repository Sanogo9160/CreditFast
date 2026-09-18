<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Services\Ocr\DocumentTextExtractor;
use App\Services\Ocr\OcrFieldParser;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OcrExtractionService
{
    public function __construct(
        protected DocumentTextExtractor $textExtractor,
        protected OcrFieldParser $fieldParser,
    ) {}

    /**
     * Extract text from the stored file, then parse targeted fields.
     * $overrideData is reserved for seeded demos without a physical file.
     *
     * @param  array{text?: string, confidence?: float, fields?: array<string, mixed>}|null  $overrideData
     */
    public function processDocumentExtraction(Document $document, ?array $overrideData = null): DocumentExtraction
    {
        $document->loadMissing(['creditRequest.client.user']);

        $payload = $overrideData !== null
            ? $this->fromOverride($document, $overrideData)
            : $this->fromStoredFile($document);

        $extraction = DocumentExtraction::updateOrCreate(
            ['document_id' => $document->id],
            [
                'extracted_text' => $payload['text'],
                'extraction_status' => $payload['status'],
                'extraction_confidence' => $payload['confidence'],
                'extracted_data' => $payload['fields'],
                'analyzed_at' => now(),
            ]
        );

        $document->update([
            'status' => $payload['status'] === 'FAILED' ? 'EXTRACTION_FAILED' : 'PROCESSED',
        ]);

        return $extraction;
    }

    /**
     * @param  array{text?: string, confidence?: float, fields?: array<string, mixed>}  $overrideData
     * @return array{text: string, confidence: float, status: string, fields: array<string, mixed>}
     */
    protected function fromOverride(Document $document, array $overrideData): array
    {
        $text = (string) ($overrideData['text'] ?? '');
        $fields = $overrideData['fields'] ?? $this->fieldParser->parse($text, $document->document_type);
        $fields['ocr_engine'] = 'override';
        $fields['ocr_source'] = 'seed_or_test';
        $fields['authenticity'] = 'OCR_IS_NOT_AUTHENTICITY';

        return [
            'text' => $text,
            'confidence' => (float) ($overrideData['confidence'] ?? 80.0),
            'status' => $text === '' ? 'FAILED' : 'COMPLETED',
            'fields' => $fields,
        ];
    }

    /**
     * @return array{text: string, confidence: float, status: string, fields: array<string, mixed>}
     */
    protected function fromStoredFile(Document $document): array
    {
        if (! Storage::exists($document->file_path)) {
            return $this->failedPayload('Fichier introuvable sur le disque de stockage.');
        }

        try {
            $absolutePath = Storage::path($document->file_path);
            $raw = $this->textExtractor->extract($absolutePath, (string) $document->mime_type);
        } catch (Throwable $exception) {
            return $this->failedPayload($exception->getMessage());
        }

        $text = $raw['text'];
        $fields = $this->fieldParser->parse($text, $document->document_type);
        $fields['ocr_engine'] = $raw['engine'];
        $fields['ocr_source'] = $raw['source'];
        $fields['authenticity'] = 'OCR_IS_NOT_AUTHENTICITY';

        if ($raw['error'] !== null) {
            $fields['ocr_error'] = $raw['error'];
        }

        $status = $this->resolveStatus($text, $fields, $raw['error']);

        return [
            'text' => $text,
            'confidence' => round((float) $raw['confidence'], 2),
            'status' => $status,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function resolveStatus(string $text, array $fields, ?string $error): string
    {
        if ($text === '' && $error !== null) {
            return 'FAILED';
        }

        if ($text === '') {
            return 'FAILED';
        }

        $hasTarget = isset($fields['verified_monthly_income'])
            || isset($fields['verified_monthly_expenses'])
            || isset($fields['average_balance'])
            || isset($fields['document_number']);

        return $hasTarget ? 'COMPLETED' : 'PARTIAL';
    }

    /**
     * @return array{text: string, confidence: float, status: string, fields: array<string, mixed>}
     */
    protected function failedPayload(string $error): array
    {
        return [
            'text' => '',
            'confidence' => 0.0,
            'status' => 'FAILED',
            'fields' => [
                'ocr_engine' => 'none',
                'ocr_source' => 'failed',
                'ocr_error' => $error,
                'authenticity' => 'OCR_IS_NOT_AUTHENTICITY',
            ],
        ];
    }
}
