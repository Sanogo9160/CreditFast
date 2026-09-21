<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegistrationTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_registers_physical_person_without_company_fields(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => ClientType::PhysicalPerson->value,
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+22376000101',
            'password' => 'MotDePasseFort8',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.client.client_type', ClientType::PhysicalPerson->value)
            ->assertJsonPath('user.client.company_name', null);

        $this->assertDatabaseHas('clients', [
            'client_type' => ClientType::PhysicalPerson->value,
            'company_name' => null,
            'registration_number' => null,
        ]);
    }

    public function test_registers_legal_entity_with_company_details(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => ClientType::LegalEntity->value,
            'first_name' => 'Fatou',
            'last_name' => 'Traore',
            'phone' => '+22376000102',
            'password' => 'MotDePasseFort8',
            'company_name' => 'SARL Textile Bamako',
            'trade_name' => 'TexBa',
            'registration_number' => 'MA.BKO.2024.B.99887',
            'legal_form' => 'SARL',
        ])
            ->assertCreated()
            ->assertJsonPath('user.client.client_type', ClientType::LegalEntity->value)
            ->assertJsonPath('user.client.company_name', 'SARL Textile Bamako')
            ->assertJsonPath('user.client.registration_number', 'MA.BKO.2024.B.99887')
            ->assertJsonPath('user.client.legal_form', 'SARL');

        $this->assertDatabaseHas('clients', [
            'client_type' => ClientType::LegalEntity->value,
            'company_name' => 'SARL Textile Bamako',
            'registration_number' => 'MA.BKO.2024.B.99887',
        ]);
    }

    public function test_returns_422_when_client_type_is_missing(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+22376000103',
            'password' => 'MotDePasseFort8',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_type']);
    }

    public function test_returns_422_when_legal_entity_misses_company_name(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => ClientType::LegalEntity->value,
            'first_name' => 'Fatou',
            'last_name' => 'Traore',
            'phone' => '+22376000104',
            'password' => 'MotDePasseFort8',
            'registration_number' => 'MA.BKO.2024.B.11111',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_returns_422_when_physical_person_sends_company_name(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => ClientType::PhysicalPerson->value,
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+22376000105',
            'password' => 'MotDePasseFort8',
            'company_name' => 'Société Interdite',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_legal_entity_can_update_company_profile_fields(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'client_type' => ClientType::LegalEntity->value,
            'first_name' => 'Moussa',
            'last_name' => 'Keita',
            'phone' => '+22376000106',
            'password' => 'MotDePasseFort8',
            'company_name' => 'SA Céréales Nord',
            'registration_number' => 'MA.BKO.2020.B.555',
            'legal_form' => 'SA',
        ])->assertCreated();

        $this->withToken($register->json('token'))
            ->putJson('/api/profile', [
                'trade_name' => 'Céréales Nord',
                'city' => 'Bamako',
            ])
            ->assertOk()
            ->assertJsonPath('client.trade_name', 'Céréales Nord')
            ->assertJsonPath('client.city', 'Bamako');

        $this->assertSame(
            'Céréales Nord',
            Client::query()->where('registration_number', 'MA.BKO.2020.B.555')->value('trade_name')
        );
    }
}
