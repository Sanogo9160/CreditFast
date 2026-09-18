<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_number' => $this->client_number,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'address' => $this->address,
            'city' => $this->city,
            'residential_zone' => $this->residential_zone,
            'occupation' => $this->occupation,
            'kyc_status' => $this->kyc_status?->value ?? $this->kyc_status,
            'institution_verified_at' => $this->institution_verified_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'financial_profile' => $this->whenLoaded('financialProfile'),
            'activities' => $this->whenLoaded('activities'),
            'kyc_documents' => $this->whenLoaded('kycDocuments'),
            'financial_accounts' => $this->whenLoaded('financialAccounts'),
            'savings_histories' => $this->whenLoaded('savingsHistories'),
        ];
    }
}
