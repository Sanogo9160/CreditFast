<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\BankAccountPartyRole;
use App\Enums\ClientType;
use App\Enums\LegalForm;
use Illuminate\Validation\Rules\Enum;

class StoreLegalEntityBankAccountApplicationRequest extends StoreBankAccountApplicationRequest
{
    protected function expectedClientType(): ClientType
    {
        return ClientType::LegalEntity;
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeSpecificRules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:200'],
            'legal_form' => ['nullable', new Enum(LegalForm::class)],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'rccm_number' => ['nullable', 'string', 'max:100'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'inps_number' => ['nullable', 'string', 'max:100'],
            'head_office_address' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'main_activity' => ['nullable', 'string', 'max:200'],
            'annual_turnover' => ['nullable', 'numeric', 'min:0'],
            'has_ubo_over_25' => ['nullable', 'boolean'],
            'indirect_control_description' => ['nullable', 'string', 'max:2000'],
            'initial_contribution_origin' => ['nullable', 'string', 'max:255'],
            'planned_operations_nature' => ['nullable', 'string', 'max:255'],
            'has_nif_copy' => ['nullable', 'boolean'],
            'has_rccm_copy' => ['nullable', 'boolean'],
            'has_approval_or_receipt_copy' => ['nullable', 'boolean'],
            'has_statutes_copy' => ['nullable', 'boolean'],
            'has_mandate_copy' => ['nullable', 'boolean'],
            'has_directors_id_copies' => ['nullable', 'boolean'],
            'has_ubo_id_copies' => ['nullable', 'boolean'],
            'parties' => ['nullable', 'array', 'max:6'],
            'parties.*.role' => ['required_with:parties', new Enum(BankAccountPartyRole::class)],
            'parties.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:3'],
            'parties.*.first_name' => ['required_with:parties', 'string', 'max:100'],
            'parties.*.last_name' => ['required_with:parties', 'string', 'max:100'],
            'parties.*.date_of_birth' => ['nullable', 'date'],
            'parties.*.birth_place' => ['nullable', 'string', 'max:150'],
            'parties.*.nationality' => ['nullable', 'string', 'max:100'],
            'parties.*.function_in_company' => ['nullable', 'string', 'max:150'],
            'parties.*.id_document_type' => ['nullable', 'string', 'max:50'],
            'parties.*.id_document_number' => ['nullable', 'string', 'max:80'],
            'parties.*.address' => ['nullable', 'string', 'max:255'],
            'parties.*.phone' => ['nullable', 'string', 'max:30'],
            'parties.*.link_with_company' => ['nullable', 'string', 'max:255'],
        ];
    }
}
