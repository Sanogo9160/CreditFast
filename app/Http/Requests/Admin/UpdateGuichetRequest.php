<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Models\Guichet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuichetRequest extends FormRequest
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
        /** @var Guichet $guichet */
        $guichet = $this->route('guichet');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('guichets', 'code')
                    ->where(fn ($q) => $q->where('caisse_id', $guichet->caisse_id))
                    ->ignore($guichet->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
