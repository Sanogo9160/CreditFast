<?php

namespace Tests\Feature;

use App\Enums\CreditRequestStatus;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceCrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake();
    }

    public function test_returns_401_when_unauthenticated_for_profile_activities(): void
    {
        $this->getJson('/api/profile/activities')->assertUnauthorized();
    }

    public function test_client_lists_updates_and_deletes_own_activity(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/profile/activities', [
            'activity_type' => 'Vente ambulante',
            'sector' => 'Commerce',
            'monthly_revenue' => 120000,
            'location' => 'Medine',
        ])->assertCreated();

        $activityId = $created->json('activity.id');

        $this->getJson('/api/profile/activities')
            ->assertOk()
            ->assertJsonFragment(['id' => $activityId]);

        $this->getJson("/api/profile/activities/{$activityId}")
            ->assertOk()
            ->assertJsonPath('activity.id', $activityId);

        $this->putJson("/api/profile/activities/{$activityId}", [
            'activity_type' => 'Vente sédentaire',
            'monthly_revenue' => 150000,
        ])->assertOk()
            ->assertJsonPath('activity.activity_type', 'Vente sédentaire');

        $this->deleteJson("/api/profile/activities/{$activityId}")
            ->assertOk()
            ->assertJsonPath('message', 'L’activité a bien été retirée.');

        $this->assertDatabaseMissing('activities', ['id' => $activityId]);
    }

    public function test_returns_422_when_deleting_activity_linked_to_submitted_request(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $activityId = $user->client->activities()->firstOrFail()->id;

        Sanctum::actingAs($user);

        $this->deleteJson("/api/profile/activities/{$activityId}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Cette activité est liée à un dossier déjà transmis. Elle ne peut pas être retirée.'
            );

        $this->assertDatabaseHas('activities', ['id' => $activityId]);
    }

    public function test_returns_404_when_client_reads_another_clients_activity(): void
    {
        $viewer = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $activityId = $other->client->activities()->firstOrFail()->id;

        Sanctum::actingAs($viewer);

        $this->getJson("/api/profile/activities/{$activityId}")->assertNotFound();
    }

    public function test_client_reads_and_updates_financial_profile(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/financial-profile')
            ->assertOk()
            ->assertJsonPath('financial_profile.monthly_income', '850000.00');

        $this->putJson('/api/profile/financial-profile', [
            'monthly_income' => 900000,
            'other_income' => 50000,
            'monthly_expenses' => 280000,
            'existing_debt_payment' => 40000,
            'dependents_count' => 3,
        ])->assertOk()
            ->assertJsonPath('financial_profile.monthly_income', '900000.00');
    }

    public function test_client_lists_and_deletes_pending_kyc_document(): void
    {
        $user = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->post('/api/profile/kyc-documents', [
            'document_type' => 'CNI',
            'document_number' => 'ML999111',
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $documentId = $created->json('kyc_document.id');

        $this->getJson('/api/profile/kyc-documents')
            ->assertOk()
            ->assertJsonFragment(['id' => $documentId]);

        $this->getJson("/api/profile/kyc-documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('kyc_document.id', $documentId);

        $this->deleteJson("/api/profile/kyc-documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('message', 'La pièce a bien été retirée.');

        $this->assertDatabaseMissing('kyc_documents', ['id' => $documentId]);
    }

    public function test_returns_422_when_deleting_verified_kyc_document(): void
    {
        $user = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->post('/api/profile/kyc-documents', [
            'document_type' => 'CNI',
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = KycDocument::query()->findOrFail($created->json('kyc_document.id'));
        $document->update(['status' => KycStatus::Verified]);

        $this->deleteJson("/api/profile/kyc-documents/{$document->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Cette pièce a déjà été examinée. Elle ne peut plus être retirée de cette façon.'
            );
    }

    public function test_client_deletes_draft_credit_request(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => 'PROFESSIONAL_WORKING_CAPITAL',
            'requested_amount' => 180000,
            'duration_months' => 6,
            'purpose' => 'Petit réassort',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');

        $this->deleteJson("/api/credit-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('message', 'La demande a bien été retirée.');

        $this->assertDatabaseMissing('credit_requests', ['id' => $requestId]);
    }

    public function test_returns_422_when_deleting_submitted_credit_request(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => 'PROFESSIONAL_WORKING_CAPITAL',
            'requested_amount' => 175000,
            'duration_months' => 6,
            'purpose' => 'Réassort après soumission',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');

        $this->postJson("/api/credit-requests/{$requestId}/submit")->assertOk();

        $this->deleteJson("/api/credit-requests/{$requestId}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Seule une demande encore en préparation peut être retirée.');

        $this->assertDatabaseHas('credit_requests', [
            'id' => $requestId,
            'status' => CreditRequestStatus::Submitted->value,
        ]);
    }

    public function test_client_lists_updates_and_deletes_pending_guarantee(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => 'PROFESSIONAL_WORKING_CAPITAL',
            'requested_amount' => 220000,
            'duration_months' => 8,
            'purpose' => 'Fonds de roulement',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 150000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');

        $guarantee = $this->postJson("/api/credit-requests/{$requestId}/guarantees", [
            'guarantee_type' => 'BOUTIQUE',
            'declared_value' => 350000,
            'description' => 'Boutique Medine',
        ])->assertCreated()
            ->assertJsonPath('guarantee.has_file', false);

        $guaranteeId = $guarantee->json('guarantee.id');

        $this->getJson("/api/credit-requests/{$requestId}/guarantees")
            ->assertOk()
            ->assertJsonFragment(['id' => $guaranteeId]);

        $this->getJson("/api/credit-requests/{$requestId}/guarantees/{$guaranteeId}")
            ->assertOk()
            ->assertJsonPath('guarantee.id', $guaranteeId);

        $this->putJson("/api/credit-requests/{$requestId}/guarantees/{$guaranteeId}", [
            'declared_value' => 400000,
            'description' => 'Boutique Medine mise à jour',
        ])->assertOk()
            ->assertJsonPath('guarantee.declared_value', '400000.00');

        $this->deleteJson("/api/credit-requests/{$requestId}/guarantees/{$guaranteeId}")
            ->assertOk();

        $this->assertDatabaseMissing('guarantees', ['id' => $guaranteeId]);
    }

    public function test_client_lists_and_deletes_draft_document(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => 'PROFESSIONAL_WORKING_CAPITAL',
            'requested_amount' => 190000,
            'duration_months' => 6,
            'purpose' => 'Stock saisonnier',
            'declared_monthly_income' => 400000,
            'declared_monthly_expenses' => 120000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');

        $uploaded = $this->post("/api/credit-requests/{$requestId}/documents", [
            'document_type' => 'RELEVE_BANCAIRE',
            'file' => UploadedFile::fake()->create('releve.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $documentId = $uploaded->json('document.id');

        $this->getJson("/api/credit-requests/{$requestId}/documents")
            ->assertOk()
            ->assertJsonFragment(['id' => $documentId]);

        $this->getJson("/api/credit-requests/{$requestId}/documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('document.id', $documentId);

        $this->deleteJson("/api/credit-requests/{$requestId}/documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('message', 'La pièce a bien été retirée du dossier.');

        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
    }

    public function test_owner_reads_and_deletes_notification(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Dossier reçu',
            'message' => 'Votre dossier a bien été enregistré.',
            'type' => 'INFO',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('notification.id', $notification->id);

        $this->deleteJson("/api/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('message', 'La notification a bien été retirée.');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_returns_403_when_deleting_another_users_notification(): void
    {
        $owner = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $notification = Notification::query()->create([
            'user_id' => $owner->id,
            'title' => 'Privé',
            'message' => 'Message réservé au propriétaire.',
            'type' => 'INFO',
        ]);

        Sanctum::actingAs($other);

        $this->deleteJson("/api/notifications/{$notification->id}")->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_admin_reads_updates_and_deactivates_staff_user(): void
    {
        $admin = User::where('email', 'admin@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $agent->createToken('session');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users/{$agent->id}")
            ->assertOk()
            ->assertJsonPath('user.email', 'agent@creditfast.com');

        $this->putJson("/api/admin/users/{$agent->id}", [
            'first_name' => 'Ousmane-Maj',
            'phone' => '+22370009999',
        ])->assertOk()
            ->assertJsonPath('user.first_name', 'Ousmane-Maj');

        $this->deleteJson("/api/admin/users/{$agent->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Le compte a été désactivé.');

        $this->assertDatabaseHas('users', [
            'id' => $agent->id,
            'status' => 'inactive',
        ]);
        $this->assertSame(0, $agent->fresh()->tokens()->count());
    }

    public function test_returns_403_when_admin_deactivates_own_account(): void
    {
        $admin = User::where('email', 'admin@creditfast.com')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$admin->id}")->assertForbidden();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'status' => 'active',
        ]);
    }

    public function test_returns_403_when_client_accesses_admin_user_or_agent_clients(): void
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($client);

        $this->getJson("/api/admin/users/{$agent->id}")->assertForbidden();
        $this->getJson('/api/agent/clients')->assertForbidden();
    }

    public function test_agent_lists_and_reads_clients(): void
    {
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail()->client;

        Sanctum::actingAs($agent);

        $this->getJson('/api/agent/clients')
            ->assertOk()
            ->assertJsonFragment(['id' => $client->id]);

        $this->getJson("/api/agent/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('client.client_number', 'CLI-000101');
    }
}
