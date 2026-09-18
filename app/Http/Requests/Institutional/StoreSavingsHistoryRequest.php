<?php

namespace App\Http\Requests\Institutional;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsHistoryRequest extends FormRequest
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
            'account_id' => ['nullable', 'exists:financial_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'total_deposits' => ['required', 'numeric', 'min:0'],
            'total_withdrawals' => ['required', 'numeric', 'min:0'],
            'deposit_count' => ['required', 'integer', 'min:0'],
            'withdrawal_count' => ['required', 'integer', 'min:0'],
            'average_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
        ];
    }
}
