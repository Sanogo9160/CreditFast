<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminResetUserPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_returns_401_when_resetting_password_without_token(): void
    {
        $agent = User::query()->where('email', 'agent@creditfast.com')->firstOrFail();

        $this->putJson("/api/admin/users/{$agent->id}/password", [
            'password' => 'NouveauMotDePasse9',
            'password_confirmation' => 'NouveauMotDePasse9',
        ])->assertUnauthorized();
    }

    #[DataProvider('nonAdminEmails')]
    public function test_returns_403_when_non_admin_resets_a_password(string $email): void
    {
        $actor = User::query()->where('email', $email)->firstOrFail();
        $target = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/users/{$target->id}/password", [
            'password' => 'NouveauMotDePasse9',
            'password_confirmation' => 'NouveauMotDePasse9',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_returns_403_when_admin_resets_own_password(): void
    {
        $admin = User::query()->where('email', 'admin@creditfast.com')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$admin->id}/password", [
            'password' => 'NouveauMotDePasse9',
            'password_confirmation' => 'NouveauMotDePasse9',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
        $this->assertFalse($admin->can('resetPassword', $admin));
    }

    public function test_returns_422_when_admin_reset_payload_is_invalid(): void
    {
        $admin = User::query()->where('email', 'admin@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$agent->id}/password", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'Le nouveau mot de passe est obligatoire.',
            ]);

        $this->putJson("/api/admin/users/{$agent->id}/password", [
            'password' => 'NouveauMotDePasse9',
            'password_confirmation' => 'AutreMotDePasse9',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'La confirmation du nouveau mot de passe ne correspond pas.',
            ]);
    }

    public function test_admin_resets_another_users_password_and_revokes_sessions(): void
    {
        $admin = User::query()->where('email', 'admin@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', 'agent@creditfast.com')->firstOrFail();
        $adminToken = $admin->createToken('admin_session')->plainTextToken;
        $agentToken = $agent->createToken('session');

        $this->assertTrue($admin->can('resetPassword', $agent));

        $this->withToken($adminToken)
            ->putJson("/api/admin/users/{$agent->id}/password", [
                'password' => 'AgentResetPass8',
                'password_confirmation' => 'AgentResetPass8',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Le mot de passe a été réinitialisé. L’utilisateur devra se reconnecter.',
            ]);

        $this->assertTrue(Hash::check('AgentResetPass8', $agent->fresh()->password));
        $this->assertSame(0, $agent->fresh()->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $agentToken->accessToken->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'USER_PASSWORD_RESET',
            'entity_type' => User::class,
            'entity_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agent->id,
            'type' => 'PASSWORD_RESET',
            'title' => 'Mot de passe réinitialisé',
        ]);

        $this->postJson('/api/auth/staff/login', [
            'email' => 'agent@creditfast.com',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/staff/login', [
            'email' => 'agent@creditfast.com',
            'password' => 'AgentResetPass8',
        ])->assertOk();
    }

    public function test_admin_user_update_does_not_change_password(): void
    {
        $admin = User::query()->where('email', 'admin@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$agent->id}", [
            'first_name' => 'Ousmane',
            'password' => 'ShouldBeIgnored8',
        ])->assertOk();

        $this->assertTrue(Hash::check('password', $agent->fresh()->password));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminEmails(): array
    {
        return [
            'client' => ['client.standard@creditfast.com'],
            'agent' => ['agent@creditfast.com'],
            'analyst' => ['analyste@creditfast.com'],
            'committee' => ['comite@creditfast.com'],
        ];
    }
}
