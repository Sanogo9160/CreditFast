<?php

namespace Tests\Feature;

use App\Enums\ComplementSubject;
use App\Enums\CreditProductType;
use App\Enums\CreditRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrontendContractEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_profile_account_check_returns_income_expenses_and_ongoing_count(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $this->getJson('/api/profile/account-check')
            ->assertOk()
            ->assertJsonStructure(['monthly_income', 'monthly_expenses', 'ongoing_credit_count'])
            ->assertJsonPath('monthly_income', 850000);
    }

    public function test_routing_catalog_lists_agencies_and_zones(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $this->getJson('/api/routing/catalog')
            ->assertOk()
            ->assertJsonStructure(['agencies', 'zones']);
    }

    public function test_admin_can_update_agent_coverage(): void
    {
        $admin = User::where('email', 'admin@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson("/api/routing/agents/{$agent->id}", [
            'agency_code' => 'BKO',
            'zone_codes' => ['BKO-CENTRE'],
            'available' => true,
        ])
            ->assertOk()
            ->assertJsonPath('agent.agency_code', 'BKO')
            ->assertJsonPath('agent.available', true);
    }

    public function test_client_credit_request_uses_account_check_snapshot_and_submit_assigns_agent(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $create = $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 400000,
            'duration_months' => 8,
            'purpose' => 'Financement de stock pour le marché',
            'declared_monthly_income' => 1,
            'declared_monthly_expenses' => 1,
        ])->assertCreated();

        $this->assertEquals(850000.0, (float) $create->json('credit_request.declared_monthly_income'));
        $id = $create->json('credit_request.id');

        $submit = $this->postJson("/api/credit-requests/{$id}/submit")->assertOk();
        $this->assertEquals(CreditRequestStatus::Submitted->value, $submit->json('credit_request.status'));
        $this->assertNotNull($submit->json('credit_request.assigned_agent_id'));
        $this->assertEquals('BKO', $submit->json('credit_request.agency_code'));
        $this->assertEquals('BKO-CENTRE', $submit->json('credit_request.zone_code'));
    }

    public function test_agent_and_analyst_cannot_read_score_analysis(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $creditRequest = $client->client->creditRequests()->firstOrFail();

        Sanctum::actingAs($agent);
        $this->getJson("/api/credit-requests/{$creditRequest->id}/analysis")->assertForbidden();
        $this->postJson("/api/credit-requests/{$creditRequest->id}/score")->assertForbidden();
    }

    public function test_auth_password_accepts_post(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $this->postJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();
    }

    public function test_savings_onboarding_endpoint(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $this->getJson('/api/profile/savings-onboarding')
            ->assertOk()
            ->assertJsonPath('has_active_savings_account', true)
            ->assertJsonPath('can_create_pre_application', false);
    }

    public function test_agent_complements_require_subject_and_detail(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $id = $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 300000,
            'duration_months' => 6,
            'purpose' => 'Besoin de trésorerie courte',
        ])->assertCreated()->json('credit_request.id');

        $this->postJson("/api/credit-requests/{$id}/submit")->assertOk();

        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/requests/{$id}/request-complements", [
            'subject' => ComplementSubject::Piece->value,
            'detail' => 'Merci de joindre le justificatif de domicile',
        ])->assertOk()
            ->assertJsonPath('credit_request.status', CreditRequestStatus::VerificationRequired->value);
    }
}
