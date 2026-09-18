<?php

namespace Tests\Feature;

use App\Enums\RepaymentCapacityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstallmentSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_simulate_installments(): void
    {
        $this->postJson('/api/simulations/installments', [
            'scenarios' => [
                ['requested_amount' => 500000, 'duration_months' => 12],
            ],
        ])->assertUnauthorized();
    }

    public function test_client_compares_amounts_and_durations_against_capacity(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/simulations/installments', [
            'scenarios' => [
                ['requested_amount' => 300000, 'duration_months' => 6],
                ['requested_amount' => 2000000, 'duration_months' => 3],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('capacity.disposable_income', 600000)
            ->assertJsonPath('scenarios.0.repayment_capacity_status', RepaymentCapacityStatus::Sufficient->value)
            ->assertJsonPath('scenarios.1.repayment_capacity_status', RepaymentCapacityStatus::Insufficient->value)
            ->assertJsonMissingPath('annual_interest_rate_percent_input');

        $this->assertCount(6, $response->json('scenarios.0.schedule_preview'));
        $this->assertFalse($response->json('scenarios.1.is_coherent'));
    }

    public function test_client_cannot_override_the_institutional_interest_rate(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/simulations/installments', [
            'annual_interest_rate_percent' => 0,
            'scenarios' => [
                ['requested_amount' => 1200000, 'duration_months' => 12],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('annual_interest_rate_percent', 12);
        $this->assertGreaterThan(100000, $response->json('scenarios.0.estimated_monthly_payment'));
    }
}
