<?php

namespace Tests\Feature;

use App\Enums\CreditRequestStatus;
use App\Models\CreditRequest;
use App\Models\User;
use App\Services\CreditWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_valid_status_transition_logging_and_notification(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        $workflow = new CreditWorkflowService;
        $updated = $workflow->transitionStatus($creditRequest, CreditRequestStatus::InAnalysis, $user, 'Début de l’analyse');

        $this->assertEquals(CreditRequestStatus::InAnalysis, $updated->status);
        $this->assertDatabaseHas('credit_status_history', [
            'credit_request_id' => $creditRequest->id,
            'new_status' => CreditRequestStatus::InAnalysis->value,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
    }
}
