<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'declared_income' => (float) $this->declared_income,
            'documented_income' => (float) $this->documented_income,
            'income_consistency_score' => (float) $this->income_consistency_score,
            'expense_score' => (float) $this->expense_score,
            'activity_score' => (float) $this->activity_score,
            'activity_vitality_score' => (float) $this->activity_vitality_score,
            'document_score' => (float) $this->document_score,
            'savings_score' => (float) $this->savings_score,
            'credit_history_score' => (float) $this->credit_history_score,
            'guarantee_score' => (float) $this->guarantee_score,
            'repayment_capacity_score' => (float) $this->repayment_capacity_score,
            'residential_zone_score' => (float) $this->residential_zone_score,
            'overall_score' => (float) $this->overall_score,
            'proposed_annual_interest_rate' => $this->proposed_annual_interest_rate !== null
                ? (float) $this->proposed_annual_interest_rate
                : null,
            'confidence_score' => (float) $this->confidence_score,
            'recommendation' => $this->recommendation?->value ?? $this->recommendation,
            'analysis_summary' => $this->analysis_summary,
            'scoring_model' => $this->whenLoaded('scoringModel', fn () => [
                'id' => $this->scoringModel->id,
                'name' => $this->scoringModel->name,
                'version' => $this->scoringModel->version,
                'scoring_mode' => $this->scoringModel->scoring_mode?->value ?? $this->scoringModel->scoring_mode,
            ]),
            'factors' => CreditScoreFactorResource::collection($this->whenLoaded('factors')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
