<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_me_and_logout_without_token_return_json_unauthorized(): void
    {
        $this->get('/api/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->post('/api/auth/logout')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_and_logout_work_with_bearer_token(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'client_type' => 'PHYSICAL_PERSON',
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'email' => 'awa.session@example.com',
            'phone' => '+22376000010',
            'password' => 'MotDePasseFort8',
        ])->assertCreated();

        $token = $register->json('token');
        $this->assertIsString($token);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'awa.session@example.com')
            ->assertJsonPath('user.role', 'client');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson([
                'message' => 'Vous avez été déconnecté. À bientôt.',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $register->json('user.id'),
        ]);
    }
}
