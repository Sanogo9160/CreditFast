<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_physical_person_client_sees_only_personal_credit_products(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $user->client->update(['client_type' => ClientType::PhysicalPerson]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/credit-products')
            ->assertOk()
            ->assertJsonPath('client_type', ClientType::PhysicalPerson->value);

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains(CreditProductType::ConsumerPersonal->value, $codes);
        $this->assertContains(CreditProductType::ProfessionalWorkingCapital->value, $codes);
        $this->assertNotContains(CreditProductType::Investment->value, $codes);
        $this->assertNotContains(CreditProductType::Factoring->value, $codes);
    }

    public function test_legal_entity_client_sees_only_business_credit_products(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'client_type' => ClientType::LegalEntity->value,
            'first_name' => 'Repr',
            'last_name' => 'Legal',
            'phone' => '+22376000999',
            'password' => 'MotDePasseFort8',
            'company_name' => 'SARL Demo Credit',
            'registration_number' => 'MA.BKO.2026.B.1',
            'legal_form' => 'SARL',
        ])->assertCreated();

        $this->withToken($register->json('token'))
            ->getJson('/api/credit-products')
            ->assertOk()
            ->assertJsonPath('client_type', ClientType::LegalEntity->value)
            ->assertJsonFragment(['code' => CreditProductType::Investment->value])
            ->assertJsonFragment(['code' => CreditProductType::Campaign->value])
            ->assertJsonMissing(['code' => CreditProductType::Mortgage->value]);
    }

    public function test_returns_422_when_physical_person_requests_company_credit_type(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::Investment->value,
            'requested_amount' => 500000,
            'duration_months' => 12,
            'purpose' => 'Investissement non autorisé',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 100000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_type']);
    }

    public function test_creates_credit_request_with_compatible_product_and_borrower_snapshot(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ConsumerPersonal->value,
            'requested_amount' => 250000,
            'duration_months' => 8,
            'purpose' => 'Trésorerie personnelle',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
        ])
            ->assertCreated()
            ->assertJsonPath('credit_request.credit_type', CreditProductType::ConsumerPersonal->value)
            ->assertJsonPath('credit_request.borrower_type', ClientType::PhysicalPerson->value)
            ->assertJsonPath('credit_request.credit_type_label', 'Prêt personnel non affecté');
    }

    public function test_staff_lists_full_catalog(): void
    {
        $admin = User::where('email', 'admin@creditfast.com')->firstOrFail();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/credit-products')->assertOk();

        $this->assertCount(count(CreditProductType::cases()), $response->json('data'));
    }
}
