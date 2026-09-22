<?php

namespace Tests\Feature;

use App\Enums\CreditRequestStatus;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Models\CreditRequest;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanDisbursementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_committee_approval_creates_loan_without_schedule(): void
    {
        $loan = $this->approveStandardClientLoan();

        $this->assertEquals(LoanStatus::Approved, $loan->status);
        $this->assertSame(15.0, (float) $loan->annual_interest_rate_percent);
        $this->assertNull($loan->disbursed_at);
        $this->assertSame(0, $loan->repayments()->count());
        $this->assertEquals(CreditRequestStatus::Approved, $loan->creditRequest->status);
    }

    public function test_committee_member_cannot_disburse(): void
    {
        $loan = $this->approveStandardClientLoan();
        $committee = User::where('email', 'comite@creditfast.com')->firstOrFail();

        Sanctum::actingAs($committee);
        $this->postJson("/api/loans/{$loan->id}/disburse")
            ->assertForbidden();
    }

    public function test_client_cannot_disburse_an_approved_loan(): void
    {
        $loan = $this->approveStandardClientLoan();
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();

        Sanctum::actingAs($client);
        $this->postJson("/api/loans/{$loan->id}/disburse")
            ->assertForbidden();
    }

    public function test_credit_agent_disburses_loan_and_generates_schedule(): void
    {
        $loan = $this->approveStandardClientLoan();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($agent);
        $response = $this->postJson("/api/loans/{$loan->id}/disburse", [
            'disbursed_at' => now()->toDateString(),
            'comment' => 'Décaissement agence ACI 2000',
        ]);

        $response->assertOk()
            ->assertJsonPath('loan.status', LoanStatus::Active->value)
            ->assertJsonPath('loan.disbursed_at', now()->toDateString());

        $this->assertCount(6, $response->json('loan.repayments'));
        $this->assertEquals(
            now()->addMonths(1)->toDateString(),
            $response->json('loan.repayments.0.due_date')
        );
        $this->assertEquals(CreditRequestStatus::Disbursed, $loan->creditRequest()->first()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOAN_DISBURSED',
            'entity_id' => $loan->id,
        ]);
    }

    public function test_cannot_disburse_the_same_loan_twice(): void
    {
        $loan = $this->disburseStandardClientLoan();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/loans/{$loan->id}/disburse")
            ->assertStatus(422);
    }

    public function test_client_can_view_own_schedule_but_not_another_clients_loan(): void
    {
        $loan = $this->disburseStandardClientLoan();
        $owner = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $other = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();

        Sanctum::actingAs($owner);
        $schedule = $this->getJson("/api/loans/{$loan->id}/repayments")
            ->assertOk()
            ->assertJsonPath('loan_status', LoanStatus::Active->value);

        $this->assertEquals((float) $loan->principal_amount, $schedule->json('funds_received'));

        Sanctum::actingAs($other);
        $this->getJson("/api/loans/{$loan->id}")
            ->assertForbidden();
    }

    public function test_agent_records_repayment_and_client_cannot(): void
    {
        $loan = $this->disburseStandardClientLoan();
        $installment = $loan->repayments()->orderBy('due_date')->firstOrFail();
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($client);
        $this->postJson("/api/loans/{$loan->id}/repayments/{$installment->id}/record", [
            'paid_amount' => 10000,
        ])->assertForbidden();

        Sanctum::actingAs($agent);
        $this->postJson("/api/loans/{$loan->id}/repayments/{$installment->id}/record", [
            'paid_amount' => (float) $installment->expected_amount,
            'payment_date' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('repayment.status', LoanRepaymentStatus::Paid->value);

        $this->assertDatabaseHas('loan_repayments', [
            'id' => $installment->id,
            'status' => LoanRepaymentStatus::Paid->value,
        ]);
    }

    protected function approveStandardClientLoan(): Loan
    {
        $client = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $committee = User::where('email', 'comite@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $client->client->id)->firstOrFail();
        $creditRequest->update(['status' => CreditRequestStatus::Committee]);

        Sanctum::actingAs($committee);
        $this->postJson("/api/committee/requests/{$creditRequest->id}/decide", [
            'decision' => 'APPROVED',
            'approved_amount' => 500000,
            'approved_duration_months' => 6,
            'comment' => 'Accord du comité pour le dossier de démonstration',
        ])->assertOk();

        return $creditRequest->fresh(['loan.repayments', 'loan.creditRequest'])->loan;
    }

    protected function disburseStandardClientLoan(): Loan
    {
        $loan = $this->approveStandardClientLoan();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/loans/{$loan->id}/disburse", [
            'disbursed_at' => now()->toDateString(),
        ])->assertOk();

        return $loan->fresh(['repayments']);
    }
}
