<?php

namespace Tests\Feature;

use App\Models\Caisse;
use App\Models\Guichet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaisseGuichetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_lists_selectable_caisses_with_guichets(): void
    {
        $client = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/caisses')->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('BKO', $response->json('data.0.code'));
        $this->assertNotEmpty($response->json('data.0.guichets'));
    }

    public function test_guichet_without_active_cash_desk_is_not_listed(): void
    {
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $orphan = Guichet::query()->create([
            'caisse_id' => $caisse->id,
            'code' => 'G99',
            'name' => 'Sans case',
            'is_active' => true,
        ]);

        $client = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        Sanctum::actingAs($client);

        $ids = collect($this->getJson("/api/caisses/{$caisse->id}/guichets")->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($orphan->id, $ids);
    }

    public function test_admin_cannot_deactivate_last_active_cash_desk_on_active_guichet(): void
    {
        $admin = User::query()->where('email', config('credit.staff.admin_email'))->firstOrFail();
        $guichet = Guichet::query()->where('code', 'G01')->whereHas('caisse', fn ($q) => $q->where('code', 'SKO'))->firstOrFail();
        $desk = $guichet->cashDesks()->where('is_active', true)->firstOrFail();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/cash-desks/{$desk->id}", [
            'is_active' => false,
        ])->assertStatus(422);
    }

    public function test_admin_can_create_update_and_delete_caisse_guichet_cash_desk(): void
    {
        $admin = User::query()->where('email', config('credit.staff.admin_email'))->firstOrFail();
        Sanctum::actingAs($admin);

        $caisse = $this->postJson('/api/admin/caisses', [
            'code' => 'KAY',
            'name' => 'Caisse de Kayes',
            'city' => 'Kayes',
        ])->assertCreated()->json('caisse');

        $this->putJson("/api/admin/caisses/{$caisse['id']}", [
            'name' => 'Caisse de Kayes (Centre)',
        ])->assertOk()
            ->assertJsonPath('caisse.name', 'Caisse de Kayes (Centre)');

        $guichet = $this->postJson('/api/admin/guichets', [
            'caisse_id' => $caisse['id'],
            'code' => 'G01',
            'name' => 'Guichet Centre',
        ])->assertCreated()->json('guichet');

        $desk = $this->postJson('/api/admin/cash-desks', [
            'guichet_id' => $guichet['id'],
            'code' => 'C01',
            'label' => 'Case 1',
        ])->assertCreated()->json('cash_desk');

        $extra = $this->postJson('/api/admin/cash-desks', [
            'guichet_id' => $guichet['id'],
            'code' => 'C02',
            'label' => 'Case 2',
        ])->assertCreated()->json('cash_desk');

        $this->deleteJson("/api/admin/cash-desks/{$extra['id']}")
            ->assertOk();

        $this->deleteJson("/api/admin/cash-desks/{$desk['id']}")
            ->assertStatus(422);

        $this->deleteJson("/api/admin/guichets/{$guichet['id']}")
            ->assertOk();

        $this->deleteJson("/api/admin/caisses/{$caisse['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('caisses', ['id' => $caisse['id']]);
    }
}
