<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'borrower_type' => $this->borrower_type?->value ?? $this->borrower_type,
            'credit_type' => $this->credit_type?->value ?? $this->credit_type,
            'credit_type_label' => $this->credit_type?->label(),
            'requested_amount' => (float) $this->requested_amount,
            'duration_months' => (int) $this->duration_months,
            'purpose' => $this->purpose,
            'declared_monthly_income' => (float) $this->declared_monthly_income,
            'declared_monthly_expenses' => (float) $this->declared_monthly_expenses,
            'ongoing_credit_count' => (int) ($this->ongoing_credit_count ?? 0),
            'estimated_monthly_payment' => (float) $this->estimated_monthly_payment,
            'disposable_income' => (float) $this->disposable_income,
            'repayment_capacity_status' => $this->repayment_capacity_status?->value ?? $this->repayment_capacity_status,
            'status' => $this->status?->value ?? $this->status,
            'agency_code' => $this->agency_code,
            'zone_code' => $this->zone_code,
            'assigned_agent_id' => $this->assigned_agent_id,
            'assignment_reason' => $this->assignment_reason,
            'complement_subject' => $this->complement_subject,
            'complement_detail' => $this->complement_detail,
            'adjourn_reason' => $this->adjourn_reason,
            'adjourn_what' => $this->adjourn_what,
            'why' => $this->adjourn_reason,
            'what' => $this->adjourn_what,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => [
                'id' => $this->assignedAgent?->id,
                'full_name' => $this->assignedAgent?->full_name,
            ]),
            'client' => new ClientResource($this->whenLoaded('client')),
            'activity' => $this->whenLoaded('activity'),
            'guarantees' => GuaranteeResource::collection($this->whenLoaded('guarantees')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'anomalies' => AnomalyResource::collection($this->whenLoaded('anomalies')),
            'latest_analysis' => new CreditAnalysisResource($this->whenLoaded('latestAnalysis')),
            'latest_committee_decision' => $this->whenLoaded('latestCommitteeDecision'),
            'loan' => new LoanResource($this->whenLoaded('loan')),
            'status_history' => $this->whenLoaded('statusHistory'),
            'human_validations' => $this->whenLoaded('humanValidations'),
            'credit_reviews' => $this->whenLoaded('creditReviews'),
        ];
    }
}
