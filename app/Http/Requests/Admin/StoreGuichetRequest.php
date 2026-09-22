<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuichetRequest extends FormRequest
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
        $caisseId = $this->route('caisse')?->id ?? $this->input('caisse_id');

        return [
            'caisse_id' => ['required', 'integer', 'exists:caisses,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('guichets', 'code')->where(fn ($q) => $q->where('caisse_id', $caisseId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
