<?php

namespace App\Http\Requests\Institutional;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return $this->user()?->can('manageInstitutionalHistory', $client) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string', 'max:50', 'unique:financial_accounts,account_number'],
            'account_type' => ['required', 'string', 'max:30'],
            'balance' => ['nullable', 'numeric'],
            'opened_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
