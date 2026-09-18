<?php

namespace App\Http\Requests\CreditRequest;

use App\Enums\CreditRequestStatus;
use App\Models\CreditRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return ($this->user()?->can('update', $creditRequest) ?? false)
            && $creditRequest->status === CreditRequestStatus::Draft;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_amount' => ['sometimes', 'numeric', 'min:10000'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'purpose' => ['sometimes', 'string', 'max:255'],
            'declared_monthly_income' => ['sometimes', 'numeric', 'min:0'],
            'declared_monthly_expenses' => ['sometimes', 'numeric', 'min:0'],
            'activity_id' => ['nullable', 'exists:activities,id'],
        ];
    }
}
