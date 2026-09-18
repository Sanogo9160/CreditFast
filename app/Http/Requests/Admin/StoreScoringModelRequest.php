<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScoringMode;
use App\Models\ScoringModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreScoringModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ScoringModel::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'version' => [
                'required',
                'string',
                'max:50',
                Rule::unique('scoring_models', 'version')->where(
                    fn ($query) => $query->where('scoring_mode', $this->input('scoring_mode'))
                ),
            ],
            'scoring_mode' => ['required', new Enum(ScoringMode::class)],
            'description' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
        ];
    }
}
