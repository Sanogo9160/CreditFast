<?php

namespace App\Http\Requests\CreditRequest;

use App\Enums\CreditRequestStatus;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuaranteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return ($this->user()?->can('addGuarantee', $creditRequest) ?? false)
            && in_array($creditRequest->status, [CreditRequestStatus::Draft, CreditRequestStatus::VerificationRequired], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guarantee_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'declared_value' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guarantee_type' => 'type de garantie',
            'declared_value' => 'valeur déclarée',
        ];
    }
}
