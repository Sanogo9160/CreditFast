<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/profile-photo')->assertUnauthorized();
        $this->post('/api/profile-photo')->assertUnauthorized();
    }

    public function test_show_returns_empty_photo_state_when_none_is_stored(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/profile-photo')
            ->assertOk()
            ->assertJsonPath('has_photo', false)
            ->assertJsonPath('profile_photo_url', null)
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_store_saves_photo_and_returns_201(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $response = $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        Storage::assertExists($user->profile_photo_path);
        $response
            ->assertJsonPath('has_photo', true)
            ->assertJsonPath('user.profile_photo_url', route('users.photo.file', $user, true));
    }

    public function test_returns_409_when_storing_a_second_photo(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJson([
                'message' => 'Une photo de profil est déjà enregistrée. Utilisez la mise à jour pour la remplacer.',
            ]);
    }

    public function test_update_replaces_existing_photo(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $oldPath = $user->fresh()->profile_photo_path;

        $this->put('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('second.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_photo', true);

        $user->refresh();

        $this->assertNotSame($oldPath, $user->profile_photo_path);
        Storage::assertMissing($oldPath);
        Storage::assertExists($user->profile_photo_path);
    }

    public function test_returns_404_when_updating_without_existing_photo(): void
    {
        Storage::fake();
        Sanctum::actingAs($this->clientUser());

        $this->put('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertNotFound()
            ->assertJson([
                'message' => 'Aucune photo de profil n’est enregistrée pour le moment.',
            ]);
    }

    public function test_destroy_removes_photo(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = $user->fresh()->profile_photo_path;

        $this->deleteJson('/api/profile-photo')
            ->assertOk()
            ->assertJsonPath('has_photo', false)
            ->assertJsonPath('profile_photo_url', null);

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::assertMissing($path);
    }

    public function test_returns_404_when_destroying_without_photo(): void
    {
        Storage::fake();
        Sanctum::actingAs($this->clientUser());

        $this->deleteJson('/api/profile-photo')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Aucune photo de profil n’est enregistrée pour le moment.',
            ]);
    }

    public function test_returns_422_when_photo_is_missing(): void
    {
        Storage::fake();
        Sanctum::actingAs($this->clientUser());

        $this->post('/api/profile-photo', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_owner_and_staff_can_download_photo_file(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->get(route('users.photo.file', $user))
            ->assertOk();

        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        Sanctum::actingAs($agent);

        $this->get(route('users.photo.file', $user))
            ->assertOk();
    }

    public function test_returns_403_when_another_client_downloads_the_file(): void
    {
        Storage::fake();
        $owner = $this->clientUser();
        Sanctum::actingAs($owner);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $other = User::query()->where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        Sanctum::actingAs($other);

        $this->getJson(route('users.photo.file', $owner))
            ->assertForbidden();
    }

    public function test_staff_can_create_and_replace_own_photo(): void
    {
        Storage::fake();
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        Sanctum::actingAs($agent);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('agent.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('user.role', 'credit_agent');
    }

    public function test_me_includes_profile_photo_url_after_upload(): void
    {
        Storage::fake();
        $user = $this->clientUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.profile_photo_url', null);

        $this->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.profile_photo_url', route('users.photo.file', $user, true));
    }

    private function clientUser(): User
    {
        return User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
    }
}
