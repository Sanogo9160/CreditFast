<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Enums\CreditRequestStatus;
use App\Models\User;
use App\Support\InstitutionalAccountRequirement;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Clients atelier (TestUsersSeeder) : scénarios avec / sans compte épargne.
 */
class TestUsersCreditClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_client_with_savings_can_create_and_submit_credit_request(): void
    {
        $user = User::query()->where('phone', '+22370000001')->firstOrFail();
        $client = $user->client()->with(['financialAccounts', 'activities'])->firstOrFail();

        $this->assertTrue($client->financialAccounts()->exists());
        $this->assertNotNull($client->financialAccounts->first(
            fn ($account) => in_array($account->account_type, ['EPARGNE', 'SAVINGS'], true)
                && in_array($account->status, ['ACTIF', 'ACTIVE'], true)
        ));
        $this->assertSame('HAM', $client->residential_zone);
        $this->assertSame('BKO-HAM', $client->financialAccounts->first()->agency_code);

        Sanctum::actingAs($user);

        $activityId = $client->activities->first()?->id;

        $create = $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 250000,
            'duration_months' => 6,
            'purpose' => 'Renouvellement de stock Hamdallaye',
            'activity_id' => $activityId,
        ])->assertCreated();

        $requestId = $create->json('credit_request.id');

        $submit = $this->postJson("/api/credit-requests/{$requestId}/submit")->assertOk();

        $this->assertSame(CreditRequestStatus::Submitted->value, $submit->json('credit_request.status'));
        $this->assertSame('BKO-HAM', $submit->json('credit_request.agency_code'));
        $this->assertSame('HAM', $submit->json('credit_request.zone_code'));
        $this->assertNotNull($submit->json('credit_request.assigned_agent_id'));
    }

    public function test_client_without_savings_is_blocked_on_credit_create(): void
    {
        $user = User::query()->where('phone', '+22370000002')->firstOrFail();
        $client = $user->client()->firstOrFail();

        $this->assertFalse($client->financialAccounts()->exists());
        $this->assertNull($client->institution_verified_at);

        Sanctum::actingAs($user);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 250000,
            'duration_months' => 6,
            'purpose' => 'Stock sans compte institutionnel',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([InstitutionalAccountRequirement::ERROR_FIELD])
            ->assertJsonFragment([
                'client' => [
                    InstitutionalAccountRequirement::message(ClientType::PhysicalPerson),
                ],
            ]);
    }

    public function test_demo_clients_login_with_atelier_password(): void
    {
        foreach (['+22370000001', '+22370000002'] as $phone) {
            $this->postJson('/api/auth/client/login', [
                'phone' => $phone,
                'password' => TestUsersSeeder::PASSWORD,
            ])
                ->assertOk()
                ->assertJsonStructure(['token', 'user']);
        }
    }
}
