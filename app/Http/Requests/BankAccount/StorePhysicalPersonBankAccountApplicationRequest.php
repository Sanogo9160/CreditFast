<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\ClientType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Validation\Rules\Enum;

class StorePhysicalPersonBankAccountApplicationRequest extends StoreBankAccountApplicationRequest
{
    protected function expectedClientType(): ClientType
    {
        return ClientType::PhysicalPerson;
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeSpecificRules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'marital_status' => ['nullable', new Enum(MaritalStatus::class)],
            'profession' => ['nullable', 'string', 'max:150'],
            'activity_sector' => ['nullable', 'string', 'max:150'],
            'id_document_type' => ['nullable', 'string', 'max:50'],
            'id_document_number' => ['nullable', 'string', 'max:80'],
            'id_issued_at' => ['nullable', 'date'],
            'id_expires_at' => ['nullable', 'date', 'after_or_equal:id_issued_at'],
            'id_issued_place' => ['nullable', 'string', 'max:150'],
            'economic_status' => ['nullable', 'string', 'max:100'],
            'employer_name' => ['nullable', 'string', 'max:150'],
            'employer_address' => ['nullable', 'string', 'max:255'],
            'estimated_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'funds_origin' => ['nullable', 'string', 'max:255'],
            'account_main_usage' => ['nullable', 'string', 'max:255'],
            'has_certified_id_copy' => ['nullable', 'boolean'],
            'has_domicile_proof' => ['nullable', 'boolean'],
            'has_income_proof' => ['nullable', 'boolean'],
        ];
    }
}
