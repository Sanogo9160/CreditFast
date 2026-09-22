<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'status' => $this->status?->value ?? $this->status,
            'caisse_id' => $this->caisse_id,
            'guichet_id' => $this->guichet_id,
            'cash_desk_id' => $this->cash_desk_id,
            'financial_account_id' => $this->financial_account_id,
            'review_comment' => $this->review_comment,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'caisse' => new CaisseResource($this->whenLoaded('caisse')),
            'guichet' => new GuichetResource($this->whenLoaded('guichet')),
            'cash_desk' => new CashDeskResource($this->whenLoaded('cashDesk')),
            'financial_account' => $this->whenLoaded('financialAccount', fn () => [
                'id' => $this->financialAccount->id,
                'account_number' => $this->financialAccount->account_number,
                'account_type' => $this->financialAccount->account_type,
                'status' => $this->financialAccount->status,
            ]),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'residential_zone' => $this->residential_zone,
            'address' => $this->address,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'birth_place' => $this->birth_place,
            'nationality' => $this->nationality,
            'country_of_origin' => $this->country_of_origin,
            'gender' => $this->gender?->value ?? $this->gender,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'marital_status' => $this->marital_status?->value ?? $this->marital_status,
            'profession' => $this->profession,
            'activity_sector' => $this->activity_sector,
            'id_document_type' => $this->id_document_type,
            'id_document_number' => $this->id_document_number,
            'id_issued_at' => $this->id_issued_at?->toDateString(),
            'id_expires_at' => $this->id_expires_at?->toDateString(),
            'id_issued_place' => $this->id_issued_place,
            'economic_status' => $this->economic_status,
            'employer_name' => $this->employer_name,
            'employer_address' => $this->employer_address,
            'estimated_monthly_income' => $this->estimated_monthly_income !== null
                ? (float) $this->estimated_monthly_income
                : null,
            'funds_origin' => $this->funds_origin,
            'account_main_usage' => $this->account_main_usage,
            'has_certified_id_copy' => $this->has_certified_id_copy,
            'has_domicile_proof' => $this->has_domicile_proof,
            'has_income_proof' => $this->has_income_proof,
            'company_name' => $this->company_name,
            'legal_form' => $this->legal_form?->value ?? $this->legal_form,
            'tax_id' => $this->tax_id,
            'rccm_number' => $this->rccm_number,
            'receipt_number' => $this->receipt_number,
            'inps_number' => $this->inps_number,
            'head_office_address' => $this->head_office_address,
            'company_email' => $this->company_email,
            'company_phone' => $this->company_phone,
            'main_activity' => $this->main_activity,
            'annual_turnover' => $this->annual_turnover !== null
                ? (float) $this->annual_turnover
                : null,
            'has_ubo_over_25' => $this->has_ubo_over_25,
            'indirect_control_description' => $this->indirect_control_description,
            'initial_contribution_origin' => $this->initial_contribution_origin,
            'planned_operations_nature' => $this->planned_operations_nature,
            'has_nif_copy' => $this->has_nif_copy,
            'has_rccm_copy' => $this->has_rccm_copy,
            'has_approval_or_receipt_copy' => $this->has_approval_or_receipt_copy,
            'has_statutes_copy' => $this->has_statutes_copy,
            'has_mandate_copy' => $this->has_mandate_copy,
            'has_directors_id_copies' => $this->has_directors_id_copies,
            'has_ubo_id_copies' => $this->has_ubo_id_copies,
            'adhesion_date' => $this->adhesion_date?->toDateString(),
            'adhesion_place' => $this->adhesion_place,
            'parties' => BankAccountApplicationPartyResource::collection($this->whenLoaded('parties')),
            'documents' => BankAccountApplicationDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
