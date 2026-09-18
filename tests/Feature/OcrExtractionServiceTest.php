<?php

namespace Tests\Feature;

use App\Models\CreditRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\Ocr\DocumentTextExtractor;
use App\Services\OcrExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeDocumentTextExtractor;
use Tests\TestCase;

class OcrExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_stores_parsed_fields_from_extracted_file_text(): void
    {
        Storage::put('ocr-tests/releve.jpg', 'binary-placeholder');

        $this->app->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor([
            'text' => "Relevé BOA Mali\nSalaire 450 000 FCFA\nSolde moyen 900 000 FCFA\nBamako Mali",
            'confidence' => 81.0,
            'engine' => 'tesseract',
            'source' => 'image_ocr',
            'error' => null,
        ]));

        $document = $this->createDocument('ocr-tests/releve.jpg', 'image/jpeg', 'RELEVE_BANCAIRE');

        $extraction = app(OcrExtractionService::class)->processDocumentExtraction($document);

        $this->assertSame('COMPLETED', $extraction->extraction_status);
        $this->assertSame(450000.0, (float) $extraction->extracted_data['verified_monthly_income']);
        $this->assertSame(900000.0, (float) $extraction->extracted_data['average_balance']);
        $this->assertSame('tesseract', $extraction->extracted_data['ocr_engine']);
        $this->assertSame('OCR_IS_NOT_AUTHENTICITY', $extraction->extracted_data['authenticity']);
        $this->assertSame('PROCESSED', $document->fresh()->status);
    }

    public function test_marks_extraction_failed_when_stored_file_is_missing(): void
    {
        $document = $this->createDocument('ocr-tests/missing.pdf', 'application/pdf', 'RELEVE_BANCAIRE');

        $extraction = app(OcrExtractionService::class)->processDocumentExtraction($document);

        $this->assertSame('FAILED', $extraction->extraction_status);
        $this->assertSame('EXTRACTION_FAILED', $document->fresh()->status);
        $this->assertArrayNotHasKey('verified_monthly_income', $extraction->extracted_data);
        $this->assertSame('OCR_IS_NOT_AUTHENTICITY', $extraction->extracted_data['authenticity']);
    }

    public function test_does_not_invent_income_when_ocr_text_has_no_amounts(): void
    {
        Storage::put('ocr-tests/cni.jpg', 'binary-placeholder');

        $this->app->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor([
            'text' => 'Carte nationale d’identité République du Mali Bamako',
            'confidence' => 70.0,
            'engine' => 'tesseract',
            'source' => 'image_ocr',
            'error' => null,
        ]));

        $document = $this->createDocument('ocr-tests/cni.jpg', 'image/jpeg', 'PIECE_IDENTITE');

        $extraction = app(OcrExtractionService::class)->processDocumentExtraction($document);

        $this->assertSame('PARTIAL', $extraction->extraction_status);
        $this->assertArrayNotHasKey('verified_monthly_income', $extraction->extracted_data);
    }

    public function test_document_upload_runs_ocr_and_returns_extraction(): void
    {
        Storage::fake('local');

        $this->app->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor([
            'text' => 'Salaire net à payer 320 000 FCFA Bamako',
            'confidence' => 79.0,
            'engine' => 'tesseract',
            'source' => 'image_ocr',
            'error' => null,
        ]));

        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->post("/api/credit-requests/{$creditRequest->id}/documents", [
            'document_type' => 'BULLETIN_PAIE',
            'file' => UploadedFile::fake()->image('bulletin.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('document.extraction.extraction_status', 'COMPLETED')
            ->assertJsonPath('document.extraction.extracted_data.verified_monthly_income', 320000);
    }

    private function createDocument(string $path, string $mime, string $type): Document
    {
        $creditRequest = CreditRequest::query()->firstOrFail();

        return $creditRequest->documents()->create([
            'document_type' => $type,
            'original_filename' => basename($path),
            'file_path' => $path,
            'mime_type' => $mime,
            'uploaded_by' => $creditRequest->client->user_id,
            'uploaded_at' => now(),
            'status' => 'UPLOADED',
        ]);
    }
}
