<?php

namespace App\Http\Requests\Simulation;

use Illuminate\Foundation\Http\FormRequest;

class SimulateInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scenarios' => ['required', 'array', 'min:1', 'max:8'],
            'scenarios.*.requested_amount' => ['required', 'numeric', 'min:10000'],
            'scenarios.*.duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'other_income' => ['nullable', 'numeric', 'min:0'],
            'monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'existing_debt_payment' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
