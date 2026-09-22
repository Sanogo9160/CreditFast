<?php

namespace App\Http\Requests\BankAccount;

use App\Models\BankAccountApplication;
use Illuminate\Foundation\Http\FormRequest;

class ApproveBankAccountApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BankAccountApplication $application */
        $application = $this->route('bankAccountApplication');

        return $this->user()?->can('review', $application) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_desk_id' => ['nullable', 'integer', 'exists:cash_desks,id'],
            'account_type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
