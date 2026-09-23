<?php

namespace Tests\Feature;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Enums\CreditRequestStatus;
use App\Enums\Gender;
use App\Models\Caisse;
use App\Models\Guichet;
use App\Models\User;
use App\Support\InstitutionalAccountRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Workflow hackathon :
 * auth → demande crédit → si pas de compte → adhésion → approve agent → crédit OK.
 */
class HackathonCreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pp_without_account_is_blocked_then_can_credit_after_adhesion_approval(): void
    {
        Storage::fake('local');

        $clientUser = User::query()->where('email', 'binta.noaccount@creditfast.com')->firstOrFail();
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $caisse = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $guichet = Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 400000,
            'duration_months' => 8,
            'purpose' => 'Achat de stock sans compte institutionnel',
            'declared_monthly_income' => 300000,
            'declared_monthly_expenses' => 120000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([InstitutionalAccountRequirement::ERROR_FIELD])
            ->assertJsonFragment([
                'client' => [
                    InstitutionalAccountRequirement::message(ClientType::PhysicalPerson),
                ],
            ]);

        $create = $this->postJson('/api/bank-account-applications/physical-person', [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'first_name' => 'Binta',
            'last_name' => 'Coulibaly',
            'phone' => '70444401',
            'city' => 'Bamako',
            'address' => 'Badalabougou',
            'date_of_birth' => '1998-09-02',
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Female->value,
            'id_document_type' => 'CNI',
            'id_document_number' => 'CNI-BINTA-001',
            'funds_origin' => 'Commerce',
            'account_main_usage' => 'Épargne et paiements',
            'has_certified_id_copy' => true,
        ])->assertCreated();

        $applicationId = $create->json('application.id');

        $this->postJson("/api/bank-account-applications/physical-person/{$applicationId}/documents", [
            'document_type' => 'CERTIFIED_ID_COPY',
            'file' => UploadedFile::fake()->create('cni-binta.pdf', 80, 'application/pdf'),
        ])->assertCreated();

        $this->postJson("/api/bank-account-applications/physical-person/{$applicationId}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Submitted->value);

        Sanctum::actingAs($agent);

        $this->postJson("/api/agent/bank-account-applications/physical-person/{$applicationId}/approve")
            ->assertOk()
            ->assertJsonPath('application.status', BankAccountApplicationStatus::Approved->value);

        $this->assertTrue($clientUser->client->fresh()->financialAccounts()->exists());

        Sanctum::actingAs($clientUser);

        $created = $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 400000,
            'duration_months' => 8,
            'purpose' => 'Achat de stock après ouverture de compte',
            'declared_monthly_income' => 300000,
            'declared_monthly_expenses' => 120000,
        ])->assertCreated();

        $requestId = $created->json('credit_request.id');

        $this->postJson("/api/credit-requests/{$requestId}/submit")
            ->assertOk()
            ->assertJsonPath('credit_request.status', CreditRequestStatus::Submitted->value);
    }

    public function test_pp_with_seeded_institutional_account_can_create_credit_request(): void
    {
        $clientUser = User::query()->where('email', 'awa.pp.banked@creditfast.com')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::ProfessionalWorkingCapital->value,
            'requested_amount' => 750000,
            'duration_months' => 10,
            'purpose' => 'Renouvellement stock commerçante Awa',
            'declared_monthly_income' => 650000,
            'declared_monthly_expenses' => 220000,
        ])
            ->assertCreated()
            ->assertJsonPath('credit_request.credit_type', CreditProductType::ProfessionalWorkingCapital->value);
    }

    public function test_pm_with_seeded_account_can_create_business_credit_request(): void
    {
        $clientUser = User::query()->where('email', 'sarl.textile@creditfast.com')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $this->assertSame(ClientType::LegalEntity, $clientUser->client->client_type);
        $this->assertTrue($clientUser->client->financialAccounts()->exists());

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::Investment->value,
            'requested_amount' => 5_000_000,
            'duration_months' => 24,
            'purpose' => 'Achat métier à tisser industriels',
            'declared_monthly_income' => 3_200_000,
            'declared_monthly_expenses' => 1_200_000,
        ])
            ->assertCreated()
            ->assertJsonPath('credit_request.credit_type', CreditProductType::Investment->value);
    }

    public function test_pm_without_account_is_directed_to_legal_entity_adhesion(): void
    {
        $clientUser = User::query()->where('email', 'sa.cereales.noaccount@creditfast.com')->firstOrFail();

        Sanctum::actingAs($clientUser);

        $this->postJson('/api/credit-requests', [
            'credit_type' => CreditProductType::Investment->value,
            'requested_amount' => 2_000_000,
            'duration_months' => 18,
            'purpose' => 'Investissement sans compte institutionnel',
            'declared_monthly_income' => 1_500_000,
            'declared_monthly_expenses' => 600000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([InstitutionalAccountRequirement::ERROR_FIELD])
            ->assertJsonFragment([
                'client' => [
                    InstitutionalAccountRequirement::message(ClientType::LegalEntity),
                ],
            ]);
    }
}
