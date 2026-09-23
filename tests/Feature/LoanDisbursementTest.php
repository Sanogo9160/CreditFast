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

    public function test_committee_approval_disburses_loan_immediately(): void
    {
        $loan = $this->approveStandardClientLoan();

        $this->assertEquals(LoanStatus::Active, $loan->status);
        $this->assertSame(15.0, (float) $loan->annual_interest_rate_percent);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertSame(6, $loan->repayments()->count());
        $this->assertGreaterThan(0, (float) $loan->funds_received);
        $this->assertEquals(CreditRequestStatus::Approved, $loan->creditRequest->status);
        $this->assertDatabaseHas('account_transactions', [
            'type' => 'LOAN_DISBURSEMENT',
            'reference' => 'EP-PRET-'.$loan->credit_request_id,
            'direction' => 'CREDIT',
        ]);
    }

    public function test_committee_member_cannot_disburse_again(): void
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

    public function test_second_disbursement_after_committee_grant_fails(): void
    {
        $loan = $this->approveStandardClientLoan();
        $agent = User::where('email', 'agent@creditfast.com')->firstOrFail();

        Sanctum::actingAs($agent);
        $this->postJson("/api/loans/{$loan->id}/disburse", [
            'disbursed_at' => now()->toDateString(),
            'comment' => 'Second décaissement',
        ])->assertStatus(422);
    }

    public function test_client_can_view_own_schedule_but_not_another_clients_loan(): void
    {
        $loan = $this->approveStandardClientLoan();
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
        $loan = $this->approveStandardClientLoan();
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
        $creditRequest->update([
            'status' => CreditRequestStatus::PendingCommittee,
            'agency_code' => 'BKO',
            'zone_code' => 'BKO-CENTRE',
        ]);

        Sanctum::actingAs($committee);
        $this->postJson("/api/committee/requests/{$creditRequest->id}/decide", [
            'decision' => 'APPROVED',
            'approved_amount' => 500000,
            'approved_duration_months' => 6,
            'comment' => 'Accord du comité pour le dossier de démonstration',
        ])->assertOk();

        return $creditRequest->fresh(['loan.repayments', 'loan.creditRequest'])->loan;
    }
}
