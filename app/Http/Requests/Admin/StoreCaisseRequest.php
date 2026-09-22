<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaisseRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:20', 'unique:caisses,code'],
            'name' => ['required', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
