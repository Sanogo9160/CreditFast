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
            'client_type' => $this->client_type?->value ?? $this->client_type,
            'company_name' => $this->company_name,
            'trade_name' => $this->trade_name,
            'registration_number' => $this->registration_number,
            'legal_form' => $this->legal_form?->value ?? $this->legal_form,
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
