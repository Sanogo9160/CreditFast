<?php

namespace Tests\Feature;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\BankAccountPartyRole;
use App\Enums\ClientType;
use App\Enums\Gender;
use App\Enums\LegalForm;
use App\Models\BankAccountApplication;
use App\Models\Caisse;
use App\Models\FinancialAccount;
use App\Models\Guichet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankAccountApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_physical_person_can_submit_and_agent_approves_with_unique_account_number(): void
    {
        Storage::fake('local');

        $clientUser = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $create = $this->postJson('/api/bank-account-applications/physical-person', [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'phone' => '70123456',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'date_of_birth' => '1990-05-12',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female->value,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI123456',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne',
            'has_certified_id_copy' => true,
        ])->assertCreated()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Draft->value);

        $id = $create->json('application.id');

        $this->postJson("/api/bank-account-applications/physical-person/{$id}/documents", [
            'document_type' => 'CERTIFIED_ID_COPY',
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->postJson("/api/bank-account-applications/physical-person/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Submitted->value);

        Sanctum::actingAs($agent);

        $approve = $this->postJson("/api/agent/bank-account-applications/physical-person/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Approved->value);

        $accountNumber = $approve->json('account_number');
        $this->assertNotNull($accountNumber);
        $this->assertMatchesRegularExpression('/^BKO-G01-\d{10}$/', $accountNumber);

        $this->assertDatabaseHas('financial_accounts', [
            'client_id' => $clientUser->client->id,
            'account_number' => $accountNumber,
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
        ]);

        $second = BankAccountApplication::query()->create([
            'client_id' => $clientUser->client->id,
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'status' => BankAccountApplicationStatus::Submitted,
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'phone' => '70123456',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'date_of_birth' => '1990-05-12',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI999',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne',
        ]);

        $approve2 = $this->postJson("/api/agent/bank-account-applications/physical-person/{$second->id}/approve")
            ->assertOk();

        $this->assertNotSame($accountNumber, $approve2->json('account_number'));
        $this->assertSame(2, FinancialAccount::query()->where('client_id', $clientUser->client->id)->whereNotNull('bank_account_application_id')->count());
    }

    public function test_physical_person_cannot_use_legal_entity_endpoints(): void
    {
        $clientUser = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $this->postJson('/api/bank-account-applications/legal-entity', [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'company_name' => 'SARL Test',
        ])->assertForbidden();
    }

    public function test_agent_can_return_physical_person_application_for_complements(): void
    {
        $clientUser = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $id = $this->postJson('/api/bank-account-applications/physical-person', [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'phone' => '70123456',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'date_of_birth' => '1990-05-12',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female->value,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI123456',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne',
        ])->json('application.id');

        $this->postJson("/api/bank-account-applications/physical-person/{$id}/submit")->assertOk();

        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/bank-account-applications/physical-person/{$id}/return", [
            'comment' => 'Merci de joindre la copie certifiée de la pièce d’identité.',
        ])
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Returned->value)
            ->assertJsonPath('returned_to_client', true);

        Sanctum::actingAs($clientUser);
        $this->putJson("/api/bank-account-applications/physical-person/{$id}", [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'has_certified_id_copy' => true,
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'phone' => '70123456',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'date_of_birth' => '1990-05-12',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female->value,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI123456',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne',
        ])->assertOk();

        $this->postJson("/api/bank-account-applications/physical-person/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Submitted->value);
    }

    public function test_client_cannot_have_two_open_applications(): void
    {
        $clientUser = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $payload = [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'phone' => '70123456',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'date_of_birth' => '1990-05-12',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female->value,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI123456',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne',
        ];

        $this->postJson('/api/bank-account-applications/physical-person', $payload)->assertCreated();
        $this->postJson('/api/bank-account-applications/physical-person', $payload)->assertStatus(422);
    }

    public function test_legal_entity_application_requires_signatory_on_submit(): void
    {
        $clientUser = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $clientUser->client->update([
            'client_type' => ClientType::LegalEntity,
            'company_name' => 'SARL Test',
            'legal_form' => LegalForm::Sarl,
            'registration_number' => 'MA.BKO.TEST.999',
        ]);

        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $id = $this->postJson('/api/bank-account-applications/legal-entity', [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'company_name' => 'SARL Test',
            'legal_form' => LegalForm::Sarl->value,
            'tax_id' => 'NIF-001',
            'rccm_number' => 'RCCM-001',
            'head_office_address' => 'Bamako centre',
            'main_activity' => 'Négoce',
            'initial_contribution_origin' => 'Apport associés',
            'planned_operations_nature' => 'Dépôts et retraits',
        ])->assertCreated()->json('application.id');

        $this->postJson("/api/bank-account-applications/legal-entity/{$id}/submit")
            ->assertStatus(422);

        $this->putJson("/api/bank-account-applications/legal-entity/{$id}", [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'company_name' => 'SARL Test',
            'legal_form' => LegalForm::Sarl->value,
            'tax_id' => 'NIF-001',
            'rccm_number' => 'RCCM-001',
            'head_office_address' => 'Bamako centre',
            'main_activity' => 'Négoce',
            'initial_contribution_origin' => 'Apport associés',
            'planned_operations_nature' => 'Dépôts et retraits',
            'parties' => [[
                'role' => BankAccountPartyRole::Signatory->value,
                'sort_order' => 1,
                'first_name' => 'Moussa',
                'last_name' => 'Diallo',
                'function_in_company' => 'Gérant',
                'nationality' => 'Malienne',
            ]],
        ])->assertOk();

        $this->postJson("/api/bank-account-applications/legal-entity/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Submitted->value);

        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/bank-account-applications/legal-entity/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Approved->value);
    }
}
