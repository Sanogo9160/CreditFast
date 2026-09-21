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
        $this->assertLessThanOrEqual(100, (float) $analysis->overall_score);
        $this->assertEquals(ScoringMode::Standard, $analysis->scoringModel->scoring_mode);
        $this->assertCount(9, $analysis->factors);
        $this->assertTrue($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ActivityVitality
        ));
        $this->assertFalse($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ResidentialZone
        ));
    }

    public function test_cold_start_credit_scoring_evaluation(): void
    {
        $user = User::where('email', 'client.coldstart@creditfast.com')->firstOrFail();
        $creditRequest = CreditRequest::where('client_id', $user->client->id)->firstOrFail();

        $engine = app(CreditScoringEngine::class);

        $this->assertEquals(ScoringMode::ColdStart, $engine->resolveScoringMode($creditRequest->loadMissing([
            'client.loans',
            'client.savingsHistories',
        ])));

        $analysis = $engine->evaluateCreditRequest($creditRequest);

        $this->assertNotNull($analysis);
        $this->assertGreaterThanOrEqual(0, (float) $analysis->overall_score);
        $this->assertLessThanOrEqual(100, (float) $analysis->overall_score);
        $this->assertEquals(ScoringMode::ColdStart, $analysis->scoringModel->scoring_mode);
        $this->assertCount(7, $analysis->factors);
        $this->assertTrue($analysis->factors->contains(
            fn ($factor): bool => $factor->factor_type === FactorType::ActivityVitality
        ));
        $this->assertFalse($analysis->factors->contains(fn ($factor): bool => in_array($factor->factor_type->value, ['savings', 'credit_history'], true)));
    }
}
