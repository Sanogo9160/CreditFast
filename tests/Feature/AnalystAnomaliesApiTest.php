<?php

namespace Tests\Feature;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Models\Anomaly;
use App\Models\CreditRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalystAnomaliesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_analyst_lists_anomalies_for_a_credit_request(): void
    {
        $analyst = User::where('email', 'analyste@creditfast.com')->firstOrFail();
        $client = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $client->client->id)->firstOrFail();

        $anomaly = Anomaly::query()->create([
            'credit_request_id' => $creditRequest->id,
            'anomaly_type' => 'MISSING_MANDATORY_DOCUMENTS',
            'severity' => AnomalySeverity::Medium,
            'description' => 'Pièces manquantes pour le dossier.',
            'status' => AnomalyStatus::Open,
        ]);

        Sanctum::actingAs($analyst);

        $this->getJson("/api/analyst/requests/{$creditRequest->id}/anomalies")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'anomaly_type', 'severity', 'status', 'description']]])
            ->assertJsonFragment([
                'id' => $anomaly->id,
                'anomaly_type' => 'MISSING_MANDATORY_DOCUMENTS',
                'status' => AnomalyStatus::Open->value,
            ]);
    }

    public function test_returns_403_when_client_lists_request_anomalies(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $client->client->id)->firstOrFail();

        Sanctum::actingAs($client);

        $this->getJson("/api/analyst/requests/{$creditRequest->id}/anomalies")
            ->assertForbidden();
    }

    public function test_returns_403_when_agent_lists_request_anomalies(): void
    {
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $client->client->id)->firstOrFail();

        Sanctum::actingAs($agent);

        $this->getJson("/api/analyst/requests/{$creditRequest->id}/anomalies")
            ->assertForbidden();
    }
}
