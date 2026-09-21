<?php

namespace App\Http\Requests\CreditRequest;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Models\Activity;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditRequest::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('credit_type') && is_string($this->input('credit_type'))) {
            $this->merge([
                'credit_type' => strtoupper(trim($this->input('credit_type'))),
            ]);
        }

        if (! $this->exists('guarantee')) {
            return;
        }

        if ($this->isBlankGuarantee($this->input('guarantee'))) {
            $this->merge(['guarantee' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credit_type' => ['required', new Enum(CreditProductType::class)],
            'requested_amount' => ['required', 'numeric', 'min:10000'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose' => ['required', 'string', 'max:255'],
            'declared_monthly_income' => ['required', 'numeric', 'min:0'],
            'declared_monthly_expenses' => ['required', 'numeric', 'min:0'],
            'activity_id' => ['nullable', 'exists:activities,id'],
            'guarantee' => ['nullable', 'array'],
            'guarantee.guarantee_type' => ['required_with:guarantee', 'string', 'max:80'],
            'guarantee.description' => ['nullable', 'string'],
            'guarantee.declared_value' => ['required_with:guarantee', 'numeric', 'min:0'],
        ];
    }

    protected function isBlankGuarantee(mixed $guarantee): bool
    {
        if ($guarantee === null) {
            return true;
        }

        if (! is_array($guarantee)) {
            return false;
        }

        return blank($guarantee['guarantee_type'] ?? null)
            && blank($guarantee['declared_value'] ?? null)
            && blank($guarantee['description'] ?? null);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $client = $this->user()?->client;
                $clientType = $client?->client_type;

                if ($clientType instanceof ClientType
                    && ! $validator->errors()->has('credit_type')
                    && $this->filled('credit_type')
                ) {
                    $product = CreditProductType::tryFrom((string) $this->input('credit_type'));

                    if ($product !== null && ! $product->isCompatibleWith($clientType)) {
                        $validator->errors()->add(
                            'credit_type',
                            'Ce type de crédit n’est pas proposé pour votre profil ('.$clientType->value.'). Consultez GET /api/credit-products.'
                        );
                    }
                }

                if ($validator->errors()->has('activity_id') || ! $this->filled('activity_id')) {
                    return;
                }

                $clientId = $client?->id;
                $belongsToClient = $clientId !== null && Activity::query()
                    ->whereKey($this->integer('activity_id'))
                    ->where('client_id', $clientId)
                    ->exists();

                if (! $belongsToClient) {
                    $validator->errors()->add('activity_id', 'Cette activité n’est pas liée à votre profil. Merci de choisir l’une de vos activités.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'credit_type' => 'type de crédit',
            'requested_amount' => 'montant demandé',
            'duration_months' => 'durée',
            'purpose' => 'objet du crédit',
            'declared_monthly_income' => 'revenu mensuel',
            'declared_monthly_expenses' => 'charges mensuelles',
            'activity_id' => 'activité',
            'guarantee.guarantee_type' => 'type de garantie',
            'guarantee.declared_value' => 'valeur de la garantie',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_type.required' => 'Choisissez un type de crédit adapté à votre profil.',
            'requested_amount.min' => 'Le montant demandé doit être d’au moins 10 000 FCFA.',
            'duration_months.max' => 'La durée maximale proposée est de 60 mois.',
        ];
    }
}
