<?php

namespace App\Http\Requests\Profile;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isLegalEntity = $this->user()?->client?->client_type === ClientType::LegalEntity;

        return [
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'residential_zone' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'company_name' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
                'string',
                'max:200',
            ],
            'trade_name' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
                'string',
                'max:200',
            ],
            'registration_number' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
                'string',
                'max:100',
            ],
            'legal_form' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
