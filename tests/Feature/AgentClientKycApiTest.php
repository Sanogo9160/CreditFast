<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentClientKycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_agent_lists_kyc_documents_for_a_client(): void
    {
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $clientUser = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $client = $clientUser->client;

        $document = KycDocument::query()->create([
            'client_id' => $client->id,
            'document_type' => 'CNI',
            'document_number' => 'ML-123456',
            'file_path' => 'demo/cni_keita.pdf',
            'status' => KycStatus::Pending,
        ]);

        Sanctum::actingAs($agent);

        $this->getJson("/api/agent/clients/{$client->id}/kyc")
            ->assertOk()
            ->assertJsonPath('client_id', $client->id)
            ->assertJsonPath('kyc_status', $client->kyc_status->value)
            ->assertJsonFragment([
                'id' => $document->id,
                'document_type' => 'CNI',
                'status' => KycStatus::Pending->value,
            ]);
    }

    public function test_returns_403_when_client_lists_another_clients_kyc(): void
    {
        $client = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.standard@creditfast.com')->firstOrFail()->client;

        Sanctum::actingAs($client);

        $this->getJson("/api/agent/clients/{$other->id}/kyc")
            ->assertForbidden();
    }
}
