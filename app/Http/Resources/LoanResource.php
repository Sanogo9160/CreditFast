<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'credit_request_id' => $this->credit_request_id,
            'principal_amount' => (float) $this->principal_amount,
            'interest_amount' => (float) $this->interest_amount,
            'total_amount' => (float) $this->total_amount,
            'duration_months' => (int) $this->duration_months,
            'annual_interest_rate_percent' => $this->annual_interest_rate_percent !== null
                ? (float) $this->annual_interest_rate_percent
                : null,
            'monthly_payment' => (float) $this->monthly_payment,
            'disbursed_at' => $this->disbursed_at?->format('Y-m-d'),
            'funds_received' => $this->disbursed_at ? (float) $this->principal_amount : 0.0,
            'maturity_date' => $this->maturity_date?->format('Y-m-d'),
            'outstanding_amount' => (float) $this->outstanding_amount,
            'status' => $this->status?->value ?? $this->status,
            'repayments' => LoanRepaymentResource::collection($this->whenLoaded('repayments')),
        ];
    }
}
