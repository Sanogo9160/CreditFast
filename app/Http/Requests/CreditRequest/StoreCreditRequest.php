<?php

namespace App\Http\Requests\CreditRequest;

use App\Models\Activity;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('activity_id') || ! $this->filled('activity_id')) {
                    return;
                }

                $clientId = $this->user()?->client?->id;
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
            'requested_amount.min' => 'Le montant demandé doit être d’au moins 10 000 FCFA.',
            'duration_months.max' => 'La durée maximale proposée est de 60 mois.',
        ];
    }
}
