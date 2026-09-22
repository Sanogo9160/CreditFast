<?php

namespace App\Http\Requests\BankAccount;

use App\Models\BankAccountApplication;

class UpdateLegalEntityBankAccountApplicationRequest extends StoreLegalEntityBankAccountApplicationRequest
{
    public function authorize(): bool
    {
        /** @var BankAccountApplication $application */
        $application = $this->route('bankAccountApplication');

        return ($this->user()?->can('update', $application) ?? false)
            && $this->user()?->client?->client_type === $this->expectedClientType();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['caisse_id'] = ['sometimes', 'required', 'integer', 'exists:caisses,id'];
        $rules['guichet_id'] = ['sometimes', 'required', 'integer', 'exists:guichets,id'];

        return $rules;
    }
}
