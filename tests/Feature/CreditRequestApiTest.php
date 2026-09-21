<?php

namespace Tests\Feature;

use App\Enums\ScoringMode;
use App\Models\CreditRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'client_type' => 'PHYSICAL_PERSON',
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

    #[DataProvider('blankGuaranteePayloads')]
    public function test_does_not_create_guarantee_when_nested_payload_is_blank(mixed $guarantee): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/credit-requests', [
            'requested_amount' => 180000,
            'duration_months' => 6,
            'purpose' => 'Petit stock sans garantie',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
            'guarantee' => $guarantee,
        ])->assertCreated();

        $requestId = $response->json('credit_request.id');

        $this->assertDatabaseMissing('guarantees', [
            'credit_request_id' => $requestId,
        ]);
        $this->assertSame([], $response->json('credit_request.guarantees'));
    }

    public function test_creates_nested_guarantee_when_it_is_filled(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/credit-requests', [
            'requested_amount' => 180000,
            'duration_months' => 6,
            'purpose' => 'Petit stock avec garantie',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
            'guarantee' => [
                'guarantee_type' => 'BOUTIQUE',
                'declared_value' => 350000,
                'description' => 'Boutique Medine',
            ],
        ])->assertCreated();

        $requestId = $response->json('credit_request.id');

        $this->assertDatabaseHas('guarantees', [
            'credit_request_id' => $requestId,
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
        ]);
        $this->assertSame('BOUTIQUE', $response->json('credit_request.guarantees.0.guarantee_type'));
    }

    public function test_returns_422_when_nested_guarantee_is_only_partially_filled(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/credit-requests', [
            'requested_amount' => 180000,
            'duration_months' => 6,
            'purpose' => 'Stock avec garantie incomplète',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
            'guarantee' => [
                'guarantee_type' => 'BOUTIQUE',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['guarantee.declared_value']);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function blankGuaranteePayloads(): array
    {
        return [
            'null' => [null],
            'empty_object' => [[]],
            'empty_strings' => [[
                'guarantee_type' => '',
                'declared_value' => '',
                'description' => '',
            ]],
            'null_fields' => [[
                'guarantee_type' => null,
                'declared_value' => null,
                'description' => null,
            ]],
        ];
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
