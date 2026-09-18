<?php

namespace Tests\Feature;

use App\Enums\CreditRequestStatus;
use App\Enums\GuaranteeVerificationStatus;
use App\Enums\KycStatus;
use App\Enums\ScoringMode;
use App\Models\CreditRequest;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecCoverageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake();
    }

    public function test_client_receives_and_reads_own_notifications_only(): void
    {
        $owner = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();

        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/credit-requests', [
            'requested_amount' => 150000,
            'duration_months' => 6,
            'purpose' => 'Petit stock',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
        ])->assertCreated();

        $this->postJson('/api/credit-requests/'.$created->json('credit_request.id').'/submit')->assertOk();

        $list = $this->getJson('/api/notifications')->assertOk();
        $this->assertGreaterThan(0, $list->json('unread_count'));
        $notificationId = $list->json('data.0.id');

        $this->postJson("/api/notifications/{$notificationId}/read")->assertOk();

        Sanctum::actingAs($other);
        $this->postJson("/api/notifications/{$notificationId}/read")->assertForbidden();
    }

    public function test_client_cannot_download_another_clients_kyc_file(): void
    {
        $owner = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($owner);
        $this->post('/api/profile/kyc-documents', [
            'document_type' => 'CNI',
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = KycDocument::where('client_id', $owner->client->id)->firstOrFail();

        Sanctum::actingAs($owner);
        $this->get("/api/kyc-documents/{$document->id}/file")->assertOk();

        Sanctum::actingAs($other);
        $this->get("/api/kyc-documents/{$document->id}/file")->assertForbidden();
    }

    public function test_agent_verifies_kyc_and_client_cannot(): void
    {
        $clientUser = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($clientUser);
        $this->post('/api/profile/kyc-documents', [
            'document_type' => 'CNI',
            'document_number' => 'ML123456',
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $document = KycDocument::where('client_id', $clientUser->client->id)->firstOrFail();

        Sanctum::actingAs($clientUser);
        $this->postJson("/api/agent/clients/{$clientUser->client->id}/kyc-documents/{$document->id}/verify", [
            'decision' => KycStatus::Verified->value,
        ])->assertForbidden();

        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/clients/{$clientUser->client->id}/kyc-documents/{$document->id}/verify", [
            'decision' => KycStatus::Verified->value,
        ])->assertOk()
            ->assertJsonPath('client.kyc_status', KycStatus::Verified->value);

        $this->assertNotNull($clientUser->client()->first()->institution_verified_at);
    }

    public function test_client_adds_guarantee_and_agent_verifies_it(): void
    {
        $clientUser = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($clientUser);
        $created = $this->postJson('/api/credit-requests', [
            'requested_amount' => 200000,
            'duration_months' => 6,
            'purpose' => 'Fonds de roulement',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 150000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');
        $guarantee = $this->postJson("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'description' => 'Boutique Medine',
        ])->assertCreated();

        $guaranteeId = $guarantee->json('guarantee.id');

        Sanctum::actingAs($agent);
        $response = $this->postJson("/api/agent/guarantees/{$guaranteeId}/verify", [
            'verification_status' => GuaranteeVerificationStatus::Verified->value,
            'verified_value' => 300000,
        ]);

        $response->assertOk();
        $this->assertEquals(300000.0, (float) $response->json('guarantee.verified_value'));
    }

    public function test_official_savings_history_cannot_be_posted_by_the_client(): void
    {
        $clientUser = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $client = $clientUser->client;

        $payload = [
            'period_start' => now()->subMonths(3)->toDateString(),
            'period_end' => now()->toDateString(),
            'total_deposits' => 100000,
            'total_withdrawals' => 20000,
            'deposit_count' => 6,
            'withdrawal_count' => 2,
            'average_balance' => 80000,
            'closing_balance' => 90000,
        ];

        Sanctum::actingAs($clientUser);
        $this->postJson("/api/agent/clients/{$client->id}/savings-history", $payload)
            ->assertForbidden();

        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/clients/{$client->id}/savings-history", $payload)
            ->assertCreated();
    }

    public function test_agent_can_request_complements_and_admin_can_list_audit_logs(): void
    {
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $admin = User::where('email', 'admin@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::firstOrFail();
        $creditRequest->update(['status' => CreditRequestStatus::Submitted]);

        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/requests/{$creditRequest->id}/request-complements", [
            'comment' => 'Merci de joindre une pièce d’identité lisible.',
        ])->assertOk()
            ->assertJsonPath('returned_to_client', true)
            ->assertJsonPath('next_actor', 'client')
            ->assertJsonPath('credit_request.status', CreditRequestStatus::VerificationRequired->value)
            ->assertJsonPath(
                'message',
                'Le dossier a été renvoyé au client afin qu’il puisse transmettre les pièces ou informations manquantes (par exemple un justificatif de domicile).'
            );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $creditRequest->client->user_id,
            'type' => 'COMPLEMENTS_REQUESTED',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/audit-logs')->assertOk();
        $this->getJson('/api/admin/scoring-models')->assertOk();
        $this->postJson('/api/admin/scoring-models', [
            'name' => 'Prototype Cold Start V1.1',
            'version' => 'V1.1',
            'scoring_mode' => ScoringMode::ColdStart->value,
            'description' => 'Version de démonstration',
        ])->assertCreated();
    }

    public function test_client_cannot_access_admin_or_create_staff(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($client);
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->postJson('/api/admin/users', [
            'first_name' => 'Hack',
            'last_name' => 'Attempt',
            'email' => 'hack@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])->assertForbidden();
    }
}
