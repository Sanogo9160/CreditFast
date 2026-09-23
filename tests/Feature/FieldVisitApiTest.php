<?php

namespace Tests\Feature;

use App\Enums\CreditRequestStatus;
use App\Enums\FieldVisitOutcome;
use App\Enums\FieldVisitStatus;
use App\Enums\FieldVisitType;
use App\Models\CreditRequest;
use App\Models\FieldVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldVisitApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_agent_can_schedule_start_and_complete_a_field_visit(): void
    {
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $creditRequest = CreditRequest::query()->where('status', CreditRequestStatus::Submitted)->first()
            ?? CreditRequest::query()->firstOrFail();

        $creditRequest->update(['status' => CreditRequestStatus::Submitted]);

        Sanctum::actingAs($agent);

        $create = $this->postJson("/api/agent/requests/{$creditRequest->id}/field-visits", [
            'visit_type' => FieldVisitType::ActivitySite->value,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'location_label' => 'Atelier Badalabougou',
            'purpose' => 'Contrôler le stock et l’activité',
        ])->assertCreated()
            ->assertJsonPath('field_visit.status', FieldVisitStatus::Scheduled->value)
            ->assertJsonPath('field_visit.visit_type', FieldVisitType::ActivitySite->value);

        $visitId = $create->json('field_visit.id');

        $this->postJson("/api/agent/field-visits/{$visitId}/start")
            ->assertOk()
            ->assertJsonPath('field_visit.status', FieldVisitStatus::InProgress->value);

        $this->postJson("/api/agent/field-visits/{$visitId}/complete", [
            'outcome' => FieldVisitOutcome::Favorable->value,
            'findings' => 'Activité réelle, stock présent et cohérent avec la demande.',
            'recommendations' => 'Poursuivre l’instruction du dossier.',
        ])
            ->assertOk()
            ->assertJsonPath('field_visit.status', FieldVisitStatus::Completed->value)
            ->assertJsonPath('field_visit.outcome', FieldVisitOutcome::Favorable->value);

        $this->assertDatabaseHas('field_visits', [
            'id' => $visitId,
            'status' => FieldVisitStatus::Completed->value,
            'outcome' => FieldVisitOutcome::Favorable->value,
        ]);
    }

    public function test_agent_can_cancel_visit_as_no_show(): void
    {
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $creditRequest = CreditRequest::query()->firstOrFail();
        $creditRequest->update(['status' => CreditRequestStatus::InAnalysis]);

        Sanctum::actingAs($agent);

        $visit = FieldVisit::query()->create([
            'credit_request_id' => $creditRequest->id,
            'client_id' => $creditRequest->client_id,
            'agent_id' => $agent->id,
            'visit_type' => FieldVisitType::Residence,
            'status' => FieldVisitStatus::Scheduled,
            'scheduled_at' => now()->addHours(2),
            'purpose' => 'Vérifier le domicile',
        ]);

        $this->postJson("/api/agent/field-visits/{$visit->id}/cancel", [
            'reason' => 'Client absent au rendez-vous convenu',
            'as_no_show' => true,
        ])
            ->assertOk()
            ->assertJsonPath('field_visit.status', FieldVisitStatus::NoShow->value);
    }

    public function test_client_cannot_schedule_field_visit(): void
    {
        $client = User::query()->where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::query()->where('client_id', $client->client->id)->firstOrFail();
        $creditRequest->update(['status' => CreditRequestStatus::Submitted]);

        Sanctum::actingAs($client);

        $this->postJson("/api/agent/requests/{$creditRequest->id}/field-visits", [
            'visit_type' => FieldVisitType::ActivitySite->value,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_cannot_schedule_visit_on_draft_request(): void
    {
        $agent = User::query()->where('email', config('credit.staff.agent_email'))->firstOrFail();
        $creditRequest = CreditRequest::query()->firstOrFail();
        $creditRequest->update(['status' => CreditRequestStatus::Draft]);

        Sanctum::actingAs($agent);

        $this->postJson("/api/agent/requests/{$creditRequest->id}/field-visits", [
            'visit_type' => FieldVisitType::Other->value,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422);
    }
}
