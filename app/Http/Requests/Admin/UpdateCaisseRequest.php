<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Models\Caisse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaisseRequest extends FormRequest
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
        /** @var Caisse $caisse */
        $caisse = $this->route('caisse');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('caisses', 'code')->ignore($caisse->id)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
