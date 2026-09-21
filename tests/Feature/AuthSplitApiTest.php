<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSplitApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_client_registers_with_phone_without_email(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => 'PHYSICAL_PERSON',
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+223 76 00 00 21',
            'password' => 'MotDePasseFort8',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.phone', '+22376000021')
            ->assertJsonPath('user.email', null);

        $this->assertDatabaseHas('users', [
            'phone' => '+22376000021',
            'email' => null,
        ]);
    }

    public function test_returns_422_when_client_registers_without_phone(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => 'PHYSICAL_PERSON',
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'email' => 'awa.nophone@example.com',
            'password' => 'MotDePasseFort8',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone' => 'Le numéro de téléphone est obligatoire pour créer un compte client.',
            ]);
    }

    public function test_returns_422_when_client_registers_with_duplicate_phone(): void
    {
        $this->postJson('/api/auth/register', [
            'client_type' => 'PHYSICAL_PERSON',
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'phone' => '+22376000022',
            'password' => 'MotDePasseFort8',
        ])->assertCreated();

        $this->postJson('/api/auth/register', [
            'client_type' => 'PHYSICAL_PERSON',
            'first_name' => 'Binta',
            'last_name' => 'Keita',
            'phone' => '+22376000022',
            'password' => 'MotDePasseFort8',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone' => 'Ce numéro de téléphone est déjà associé à un compte. Vous pouvez vous connecter ou en indiquer un autre.',
            ]);
    }

    public function test_client_logs_in_with_phone_and_not_with_staff_email_endpoint(): void
    {
        $this->postJson('/api/auth/client/login', [
            'phone' => '+22375112233',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'client.standard@creditfast.com')
            ->assertJsonPath('user.role', 'client');

        $this->postJson('/api/auth/staff/login', [
            'email' => 'client.standard@creditfast.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'L’adresse e-mail ou le mot de passe ne correspond pas. Vous pouvez réessayer.',
            ]);
    }

    public function test_staff_logs_in_with_email_and_not_with_client_phone_endpoint(): void
    {
        $this->postJson('/api/auth/staff/login', [
            'email' => 'admin@creditfast.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $admin = User::query()->where('email', 'admin@creditfast.com')->firstOrFail();

        $this->postJson('/api/auth/client/login', [
            'phone' => $admin->phone,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone' => 'Le numéro de téléphone ou le mot de passe ne correspond pas. Vous pouvez réessayer.',
            ]);
    }

    public function test_legacy_mixed_login_route_is_removed(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@creditfast.com',
            'password' => 'password',
        ])->assertNotFound();
    }
}
