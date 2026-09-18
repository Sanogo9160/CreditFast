<?php

namespace App\Http\Requests\Institutional;

use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FinancialAccount $account */
        $account = $this->route('financialAccount');

        return $this->user()?->can('manageInstitutionalHistory', $account->client) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_type' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
