<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRepaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'expected_amount' => (float) $this->expected_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => round((float) $this->expected_amount - (float) $this->paid_amount, 2),
            'days_late' => (int) $this->days_late,
            'status' => $this->status?->value ?? $this->status,
        ];
    }
}
