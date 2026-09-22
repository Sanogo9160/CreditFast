<?php

namespace App\Http\Requests\BankAccount;

use App\Models\BankAccountApplication;
use Illuminate\Foundation\Http\FormRequest;

class RejectBankAccountApplicationRequest extends FormRequest
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
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
