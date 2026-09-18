<?php

namespace App\Http\Requests\CreditRequest;

use App\Enums\CreditRequestStatus;
use App\Enums\GuaranteeVerificationStatus;
use App\Models\CreditRequest;
use App\Models\Guarantee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuaranteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');
        /** @var Guarantee $guarantee */
        $guarantee = $this->route('guarantee');

        return ($this->user()?->can('addGuarantee', $creditRequest) ?? false)
            && $guarantee->credit_request_id === $creditRequest->id
            && $guarantee->verification_status === GuaranteeVerificationStatus::Pending
            && in_array($creditRequest->status, [CreditRequestStatus::Draft, CreditRequestStatus::VerificationRequired], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guarantee_type' => ['sometimes', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'declared_value' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
