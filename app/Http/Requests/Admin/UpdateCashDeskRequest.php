<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Models\CashDesk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashDeskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CashDesk $cashDesk */
        $cashDesk = $this->route('cashDesk');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('cash_desks', 'code')
                    ->where(fn ($q) => $q->where('guichet_id', $cashDesk->guichet_id))
                    ->ignore($cashDesk->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
