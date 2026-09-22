<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashDeskRequest extends FormRequest
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
        $guichetId = $this->route('guichet')?->id ?? $this->input('guichet_id');

        return [
            'guichet_id' => ['required', 'integer', 'exists:guichets,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cash_desks', 'code')->where(fn ($q) => $q->where('guichet_id', $guichetId)),
            ],
            'label' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
