<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountApplicationPartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role?->value ?? $this->role,
            'sort_order' => $this->sort_order,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'birth_place' => $this->birth_place,
            'nationality' => $this->nationality,
            'function_in_company' => $this->function_in_company,
            'id_document_type' => $this->id_document_type,
            'id_document_number' => $this->id_document_number,
            'address' => $this->address,
            'phone' => $this->phone,
            'link_with_company' => $this->link_with_company,
            'has_photo' => $this->photo_path !== null,
            'has_signature' => $this->signature_path !== null,
        ];
    }
}
