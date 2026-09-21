<?php

namespace Tests\Feature;

use App\Models\CreditRequest;
use App\Models\User;
use App\Services\ActivityVitalityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityVitalityScorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-21 12:00:00');
        $this->seed();
    }

    public function test_missing_revenue_evidence_scores_neutral_fifty_not_zero(): void
    {
        $request = $this->creditRequestFor('client.coldstart@creditfast.com');
        $request->documents()->delete();
        $request->unsetRelation('documents');
        $request->load(['documents', 'activity', 'client.financialAccounts.transactions']);

        $scorer = app(ActivityVitalityScorer::class);

        $this->assertSame(50.0, $scorer->recencyScore($request));
        $this->assertSame(50.0, $scorer->rhythmScore($request));
        $this->assertSame(90.0, $scorer->fitScore($request));
        $this->assertSame(60.0, $scorer->score($request)['score']);
    }

    public function test_twelve_month_stock_loan_is_a_cycle_misfit(): void
    {
        $request = $this->creditRequestFor('client.standard@creditfast.com');

        $scorer = app(ActivityVitalityScorer::class);

        $this->assertSame(40.0, $scorer->fitScore($request));
        $this->assertSame(67.25, $scorer->score($request)['score']);
    }

    public function test_ten_month_machine_loan_matches_equipment_cycle(): void
    {
        $request = $this->creditRequestFor('client.coldstart@creditfast.com');

        $scorer = app(ActivityVitalityScorer::class);

        $this->assertSame(90.0, $scorer->fitScore($request));
        $this->assertSame(79.75, $scorer->score($request)['score']);
    }

    public function test_four_recent_revenue_documents_raise_rhythm(): void
    {
        $request = $this->creditRequestFor('client.coldstart@creditfast.com');

        foreach (['carnet_1.pdf', 'carnet_2.pdf', 'carnet_3.pdf'] as $filename) {
            $request->documents()->create([
                'document_type' => 'PREUVE_REVENU',
                'original_filename' => $filename,
                'file_path' => 'demo/'.$filename,
                'mime_type' => 'application/pdf',
                'uploaded_by' => $request->client->user_id,
                'uploaded_at' => now()->subDays(5),
                'status' => 'PROCESSED',
            ]);
        }

        $request->unsetRelation('documents');
        $request->load(['documents', 'activity', 'client.financialAccounts.transactions']);

        $scorer = app(ActivityVitalityScorer::class);

        $this->assertSame(90.0, $scorer->rhythmScore($request));
        $this->assertSame(92.0, $scorer->score($request)['score']);
    }

    private function creditRequestFor(string $email): CreditRequest
    {
        $user = User::where('email', $email)->firstOrFail();

        return CreditRequest::query()
            ->where('client_id', $user->client->id)
            ->with(['documents', 'activity', 'client.financialAccounts.transactions'])
            ->firstOrFail();
    }
}
