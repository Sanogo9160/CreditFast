<?php

namespace App\Http\Requests\Profile;

use App\Enums\ClientType;
use App\Enums\LegalForm;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['company_name', 'trade_name', 'registration_number', 'legal_form'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            $merge[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if (isset($merge['legal_form']) && is_string($merge['legal_form'])) {
            $merge['legal_form'] = strtoupper($merge['legal_form']);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Client|null $client */
        $client = $this->user()?->client;
        $isLegalEntity = $client?->client_type === ClientType::LegalEntity;

        return [
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'residential_zone' => ['nullable', 'string', 'max:100'],
            'occupation' => [
                Rule::prohibitedIf($isLegalEntity),
                'nullable',
                'string',
                'max:150',
            ],
            'company_name' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'sometimes',
                'required',
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
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('clients', 'registration_number')->ignore($client?->id),
            ],
            'legal_form' => [
                Rule::prohibitedIf(! $isLegalEntity),
                'sometimes',
                'required',
                new Enum(LegalForm::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'raison sociale',
            'trade_name' => 'nom commercial',
            'registration_number' => 'RCCM / NIF',
            'legal_form' => 'forme juridique',
            'occupation' => 'profession',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'La raison sociale ne peut pas être vide pour une personne morale.',
            'registration_number.required' => 'Le numéro RCCM / NIF ne peut pas être vide pour une personne morale.',
            'registration_number.unique' => 'Ce numéro d’immatriculation est déjà associé à une entreprise.',
            'legal_form.required' => 'La forme juridique ne peut pas être vide pour une personne morale.',
            'occupation.prohibited' => 'La profession individuelle ne s’applique pas à une personne morale.',
        ];
    }
}
