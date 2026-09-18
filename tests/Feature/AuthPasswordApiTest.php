<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_returns_401_when_changing_password_without_token(): void
    {
        $this->putJson('/api/auth/password', [
            'current_password' => 'MotDePasseFort8',
            'password' => 'NouveauMotDePasse9',
            'password_confirmation' => 'NouveauMotDePasse9',
        ])->assertUnauthorized();
    }

    public function test_returns_422_when_password_payload_is_empty(): void
    {
        $token = $this->registerToken();

        $this->withToken($token)
            ->putJson('/api/auth/password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password' => 'L’ancien mot de passe est obligatoire.',
                'password' => 'Le nouveau mot de passe est obligatoire.',
            ]);
    }

    public function test_returns_422_when_current_password_does_not_match(): void
    {
        $token = $this->registerToken();
        $user = User::query()->where('email', 'awa.password@example.com')->firstOrFail();

        $this->withToken($token)
            ->putJson('/api/auth/password', [
                'current_password' => 'MauvaisMotDePasse',
                'password' => 'NouveauMotDePasse9',
                'password_confirmation' => 'NouveauMotDePasse9',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password' => 'L’ancien mot de passe ne correspond pas.',
            ]);

        $this->assertTrue(Hash::check('MotDePasseFort8', $user->fresh()->password));
    }

    public function test_returns_422_when_new_password_matches_current_password(): void
    {
        $token = $this->registerToken();

        $this->withToken($token)
            ->putJson('/api/auth/password', [
                'current_password' => 'MotDePasseFort8',
                'password' => 'MotDePasseFort8',
                'password_confirmation' => 'MotDePasseFort8',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'Le nouveau mot de passe doit être différent de l’ancien.',
            ]);
    }

    public function test_authenticated_user_changes_password_and_other_tokens_are_revoked(): void
    {
        $token = $this->registerToken();
        $user = User::query()->where('email', 'awa.password@example.com')->firstOrFail();
        $otherAccess = $user->createToken('other_session');

        $this->withToken($token)
            ->putJson('/api/auth/password', [
                'current_password' => 'MotDePasseFort8',
                'password' => 'NouveauMotDePasse9',
                'password_confirmation' => 'NouveauMotDePasse9',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Votre mot de passe a bien été modifié.',
            ]);

        $this->assertTrue(Hash::check('NouveauMotDePasse9', $user->fresh()->password));
        $this->assertFalse(Hash::check('MotDePasseFort8', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherAccess->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $user->tokens()->firstOrFail()->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'awa.password@example.com');

        $this->postJson('/api/auth/client/login', [
            'phone' => '+22376000011',
            'password' => 'MotDePasseFort8',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/client/login', [
            'phone' => '+22376000011',
            'password' => 'NouveauMotDePasse9',
        ])->assertOk()
            ->assertJsonPath('user.phone', '+22376000011');
    }

    public function test_staff_user_can_change_own_password(): void
    {
        $agent = User::query()->where('email', 'agent@creditfast.com')->firstOrFail();
        $token = $agent->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/auth/password', [
                'current_password' => 'password',
                'password' => 'AgentNouveau8',
                'password_confirmation' => 'AgentNouveau8',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('AgentNouveau8', $agent->fresh()->password));
    }

    private function registerToken(): string
    {
        $register = $this->postJson('/api/auth/register', [
            'first_name' => 'Awa',
            'last_name' => 'Diarra',
            'email' => 'awa.password@example.com',
            'phone' => '+22376000011',
            'password' => 'MotDePasseFort8',
        ])->assertCreated();

        $token = $register->json('token');
        $this->assertIsString($token);

        return $token;
    }
}
