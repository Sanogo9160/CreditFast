<?php

namespace Tests\Feature;

use App\Models\Guarantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuaranteeFileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->post('/api/credit-requests/1/guarantees', [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'file' => UploadedFile::fake()->create('titre.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        $this->getJson('/api/guarantees/1/file')->assertUnauthorized();
    }

    public function test_client_stores_guarantee_with_optional_file_and_downloads_it(): void
    {
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $requestId = $this->createDraftCreditRequest();

        $created = $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'FONCIER',
            'declared_value' => 800000,
            'description' => 'Titre foncier Bamako',
            'file' => UploadedFile::fake()->create('titre-foncier.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $guaranteeId = $created->json('guarantee.id');
        $guarantee = Guarantee::query()->findOrFail($guaranteeId);

        $this->assertNotNull($guarantee->file_path);
        Storage::assertExists($guarantee->file_path);

        $created
            ->assertJsonPath('guarantee.has_file', true)
            ->assertJsonPath('guarantee.original_filename', 'titre-foncier.pdf')
            ->assertJsonPath('guarantee.file_url', route('guarantees.file', $guarantee, true))
            ->assertJsonMissingPath('guarantee.file_path');

        $this->get("/api/guarantees/{$guaranteeId}/file")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_json_create_without_file_still_succeeds(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $this->postJson("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'description' => 'Boutique Medine',
        ])->assertCreated()
            ->assertJsonPath('guarantee.has_file', false)
            ->assertJsonPath('guarantee.file_url', null);
    }

    public function test_rejects_unsupported_guarantee_file_type_with_422(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'file' => UploadedFile::fake()->create('note.txt', 20, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Merci de joindre un fichier PDF, JPG ou PNG pour la garantie.');
    }

    public function test_ignores_unexpected_file_path_in_json_payload(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->postJson("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'file_path' => 'guarantee_documents/evil.pdf',
        ])->assertCreated();

        $this->assertDatabaseHas('guarantees', [
            'id' => $created->json('guarantee.id'),
            'file_path' => null,
        ]);
    }

    public function test_update_replaces_existing_guarantee_file(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'MATERIEL',
            'declared_value' => 200000,
            'file' => UploadedFile::fake()->create('ancien.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $guaranteeId = $created->json('guarantee.id');
        $oldPath = Guarantee::query()->findOrFail($guaranteeId)->file_path;

        $this->put("/api/credit-requests/{$requestId}/guarantees/{$guaranteeId}", [
            'declared_value' => 220000,
            'file' => UploadedFile::fake()->image('nouveau.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('guarantee.has_file', true)
            ->assertJsonPath('guarantee.original_filename', 'nouveau.jpg');

        $guarantee = Guarantee::query()->findOrFail($guaranteeId);
        Storage::assertMissing($oldPath);
        Storage::assertExists($guarantee->file_path);
        $this->assertNotSame($oldPath, $guarantee->file_path);
    }

    public function test_destroy_deletes_stored_guarantee_file(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'EPARGNE',
            'declared_value' => 150000,
            'file' => UploadedFile::fake()->create('livret.pdf', 30, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $guaranteeId = $created->json('guarantee.id');
        $path = Guarantee::query()->findOrFail($guaranteeId)->file_path;

        $this->deleteJson("/api/credit-requests/{$requestId}/guarantees/{$guaranteeId}")
            ->assertOk();

        Storage::assertMissing($path);
        $this->assertDatabaseMissing('guarantees', ['id' => $guaranteeId]);
    }

    public function test_returns_404_when_downloading_guarantee_without_file(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->postJson("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'CAUTION',
            'declared_value' => 100000,
        ])->assertCreated();

        $this->getJson('/api/guarantees/'.$created->json('guarantee.id').'/file')
            ->assertNotFound()
            ->assertJsonPath('message', 'Aucun fichier n’est associé à cette garantie pour le moment.');
    }

    public function test_forbids_another_client_from_downloading_guarantee_file(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'file' => UploadedFile::fake()->create('titre.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $guaranteeId = $created->json('guarantee.id');

        Sanctum::actingAs(User::query()->where('email', 'client.coldstart@creditfast.com')->firstOrFail());

        $this->get("/api/guarantees/{$guaranteeId}/file")->assertForbidden();
    }

    public function test_agent_can_download_client_guarantee_file(): void
    {
        Sanctum::actingAs($this->clientUser());

        $requestId = $this->createDraftCreditRequest();

        $created = $this->post("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'file' => UploadedFile::fake()->create('titre.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        Sanctum::actingAs(User::query()->where('email', 'agent@creditfast.com')->firstOrFail());

        $this->get('/api/guarantees/'.$created->json('guarantee.id').'/file')->assertOk();
    }

    private function clientUser(): User
    {
        return User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
    }

    private function createDraftCreditRequest(): int
    {
        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => 'PROFESSIONAL_WORKING_CAPITAL',
            'requested_amount' => 220000,
            'duration_months' => 8,
            'purpose' => 'Fonds de roulement',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 150000,
        ])->assertCreated();

        return (int) $created->json('credit_request.id');
    }
}
