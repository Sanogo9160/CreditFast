<?php

namespace App\Http\Requests\CreditRequest;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Enums\CreditRequestStatus;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return ($this->user()?->can('update', $creditRequest) ?? false)
            && in_array($creditRequest->status, [
                CreditRequestStatus::Draft,
                CreditRequestStatus::VerificationRequired,
            ], true);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('credit_type') && is_string($this->input('credit_type'))) {
            $this->merge([
                'credit_type' => strtoupper(trim($this->input('credit_type'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credit_type' => ['sometimes', new Enum(CreditProductType::class)],
            'requested_amount' => ['sometimes', 'numeric', 'min:10000'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'purpose' => ['sometimes', 'string', 'max:255'],
            'activity_id' => ['nullable', 'exists:activities,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('credit_type') || ! $this->filled('credit_type')) {
                    return;
                }

                /** @var CreditRequest $creditRequest */
                $creditRequest = $this->route('creditRequest');
                $clientType = $creditRequest->borrower_type
                    ?? $creditRequest->client?->client_type
                    ?? $this->user()?->client?->client_type;

                if (! $clientType instanceof ClientType) {
                    return;
                }

                $product = CreditProductType::tryFrom((string) $this->input('credit_type'));

                if ($product !== null && ! $product->isCompatibleWith($clientType)) {
                    $validator->errors()->add(
                        'credit_type',
                        'Ce type de crédit n’est pas proposé pour le profil de ce dossier.'
                    );
                }
            },
        ];
    }
}
