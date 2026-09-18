<?php

namespace Tests\Feature;

use App\Enums\ScoringMode;
use App\Models\CreditRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_authentication_api(): void
    {
        $response = $this->postJson('/api/auth/client/login', [
            'phone' => '+22375112233',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_register_always_creates_a_client_and_ignores_role_escalation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+22370001122',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.phone', '+22370001122');

        $this->assertDatabaseHas('users', [
            'phone' => '+22370001122',
            'email' => null,
        ]);
    }

    public function test_create_and_submit_credit_request_api(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/credit-requests', [
            'requested_amount' => 1000000,
            'duration_months' => 12,
            'purpose' => 'Extension magasin de vente',
            'declared_monthly_income' => 600000,
            'declared_monthly_expenses' => 200000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('credit_request.requested_amount', 1000000);

        $requestId = $response->json('credit_request.id');

        $submitResponse = $this->postJson("/api/credit-requests/{$requestId}/submit");
        $submitResponse->assertStatus(200)
            ->assertJsonPath('credit_request.status', 'SUBMITTED');
    }

    public function test_client_cannot_trigger_scoring(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        Sanctum::actingAs($user);
        $this->postJson("/api/credit-requests/{$creditRequest->id}/score")
            ->assertForbidden();
    }

    public function test_analyst_can_trigger_scoring_evaluation(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $analyst = User::where('email', 'analyste@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $client->client->id)->firstOrFail();

        Sanctum::actingAs($analyst);
        $response = $this->postJson("/api/credit-requests/{$creditRequest->id}/score");

        $response->assertStatus(200)
            ->assertJsonStructure(['analysis' => ['overall_score', 'recommendation', 'factors']])
            ->assertJsonPath('analysis.scoring_model.scoring_mode', ScoringMode::Standard->value);
    }

    public function test_client_cannot_access_analyst_queue(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);
        $this->getJson('/api/analyst/requests')
            ->assertForbidden();
    }

    public function test_client_cannot_view_another_clients_request(): void
    {
        $viewer = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $otherRequest = CreditRequest::where('client_id', $other->client->id)->firstOrFail();

        Sanctum::actingAs($viewer);
        $this->getJson("/api/credit-requests/{$otherRequest->id}")
            ->assertForbidden();
    }
}
