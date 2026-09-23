<?php

namespace Tests\Feature;

use App\Enums\FactorType;
use App\Enums\ScoringMode;
use App\Models\CreditRequest;
use App\Models\User;
use App\Services\CreditScoringEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_standard_credit_scoring_evaluation(): void
    {
        $user = User::where('email', 'client.standard@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        $engine = app(CreditScoringEngine::class);
        $analysis = $engine->evaluateCreditRequest($creditRequest);

        $this->assertNotNull($analysis);
        $this->assertGreaterThanOrEqual(0, (float) $analysis->overall_score);
        $this->assertLessThanOrEqual(
            (float) config('credit.scoring.overall_score_ceiling'),
            (float) $analysis->overall_score
        );
        $this->assertNotEquals(100.0, (float) $analysis->overall_score);
        $this->assertNotNull($analysis->proposed_annual_interest_rate);
        $this->assertSame(15.0, (float) $analysis->proposed_annual_interest_rate);
        $this->assertEquals(ScoringMode::Standard, $analysis->scoringModel->scoring_mode);
        $this->assertCount(9, $analysis->factors);
        $this->assertTrue($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ActivityVitality
        ));
        $this->assertFalse($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ResidentialZone
        ));
    }

    public function test_scoring_uses_standard_mode_for_all_eligible_clients(): void
    {
        $user = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        $engine = app(CreditScoringEngine::class);

        $this->assertEquals(ScoringMode::Standard, $engine->resolveScoringMode($creditRequest->loadMissing([
            'client.financialAccounts',
            'client.loans',
            'client.savingsHistories',
        ])));

        $analysis = $engine->evaluateCreditRequest($creditRequest);

        $this->assertNotNull($analysis);
        $this->assertEquals(ScoringMode::Standard, $analysis->scoringModel->scoring_mode);
        $this->assertLessThanOrEqual(
            (float) config('credit.scoring.overall_score_ceiling'),
            (float) $analysis->overall_score
        );
        $this->assertCount(9, $analysis->factors);
        $this->assertTrue($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ActivityVitality
        ));
        $this->assertTrue($analysis->factors->contains(
            fn ($factor): bool => in_array($factor->factor_type->value, ['savings', 'credit_history'], true)
        ));
    }

    public function test_scoring_rejects_client_without_financial_account(): void
    {
        $user = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $client = $user->client;
        $client->financialAccounts()->delete();
        $creditRequest = CreditRequest::where('client_id', $client->id)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('compte institutionnel');

        app(CreditScoringEngine::class)->evaluateCreditRequest($creditRequest->fresh());
    }
}
