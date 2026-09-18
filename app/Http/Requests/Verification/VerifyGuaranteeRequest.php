<?php

namespace App\Http\Requests\Verification;

use App\Enums\GuaranteeVerificationStatus;
use App\Models\Guarantee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class VerifyGuaranteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Guarantee $guarantee */
        $guarantee = $this->route('guarantee');

        return $this->user()?->can('verify', $guarantee) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', new Enum(GuaranteeVerificationStatus::class)],
            'verified_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
