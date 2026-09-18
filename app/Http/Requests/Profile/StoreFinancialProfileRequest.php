<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'other_income' => ['nullable', 'numeric', 'min:0'],
            'monthly_expenses' => ['required', 'numeric', 'min:0'],
            'existing_debt_payment' => ['nullable', 'numeric', 'min:0'],
            'dependents_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
