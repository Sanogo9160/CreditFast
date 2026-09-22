<?php

namespace App\Http\Requests\Auth;

use App\Enums\ClientType;
use App\Enums\LegalForm;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $merge = [
            'phone' => PhoneNumber::normalize($this->input('phone')),
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'client_type' => is_string($this->input('client_type'))
                ? strtoupper(trim($this->input('client_type')))
                : $this->input('client_type'),
        ];

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

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isLegalEntity = $this->input('client_type') === ClientType::LegalEntity->value;

        return [
            'client_type' => ['required', new Enum(ClientType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'company_name' => [
                Rule::requiredIf($isLegalEntity),
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
                Rule::requiredIf($isLegalEntity),
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
                'string',
                'max:100',
                Rule::unique('clients', 'registration_number'),
            ],
            'legal_form' => [
                Rule::requiredIf($isLegalEntity),
                Rule::prohibitedIf(! $isLegalEntity),
                'nullable',
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
            'client_type' => 'type de compte',
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'numéro de téléphone',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
            'company_name' => 'raison sociale',
            'trade_name' => 'nom commercial',
            'registration_number' => 'RCCM / NIF',
            'legal_form' => 'forme juridique',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_type.required' => 'Indiquez si le compte est une personne physique ou une personne morale.',
            'phone.required' => 'Le numéro de téléphone est obligatoire pour créer un compte client.',
            'phone.unique' => 'Ce numéro de téléphone est déjà associé à un compte. Vous pouvez vous connecter ou en indiquer un autre.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte. Vous pouvez vous connecter ou en indiquer une autre.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'company_name.required' => 'La raison sociale est obligatoire pour une personne morale.',
            'registration_number.required' => 'Le numéro RCCM ou NIF est obligatoire pour une personne morale.',
            'registration_number.unique' => 'Ce numéro d’immatriculation est déjà associé à une entreprise.',
            'legal_form.required' => 'La forme juridique est obligatoire pour une personne morale (ex. SARL, SA, GIE).',
            'company_name.prohibited' => 'La raison sociale ne s’applique qu’aux personnes morales.',
            'trade_name.prohibited' => 'Le nom commercial ne s’applique qu’aux personnes morales.',
            'registration_number.prohibited' => 'Le numéro d’immatriculation ne s’applique qu’aux personnes morales.',
            'legal_form.prohibited' => 'La forme juridique ne s’applique qu’aux personnes morales.',
        ];
    }
}
