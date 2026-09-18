<?php

namespace App\Http\Requests\Analyst;

use App\Enums\ValidationDecision;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreHumanValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return $this->user()?->can('review', $creditRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return [
            'document_id' => [
                'nullable',
                Rule::exists('documents', 'id')->where('credit_request_id', $creditRequest->id),
            ],
            'validation_type' => ['required', 'string', 'max:50'],
            'decision' => ['required', new Enum(ValidationDecision::class)],
            'comment' => ['nullable', 'string'],
        ];
    }
}
