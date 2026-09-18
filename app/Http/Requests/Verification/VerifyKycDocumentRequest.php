<?php

namespace App\Http\Requests\Verification;

use App\Enums\KycStatus;
use App\Models\KycDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class VerifyKycDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var KycDocument $document */
        $document = $this->route('kycDocument');

        return $this->user()?->can('verify', $document) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', new Enum(KycStatus::class)],
            'rejection_reason' => ['required_if:decision,REJECTED', 'nullable', 'string', 'min:5'],
        ];
    }
}
