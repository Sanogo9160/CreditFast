<?php

namespace App\Http\Requests\Admin;

use App\Enums\FactorType;
use App\Models\ScoringModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ScoringModel $model */
        $model = $this->route('scoringModel');

        return $this->user()?->can('update', $model) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rule_code' => ['required', 'string', 'max:50'],
            'rule_name' => ['required', 'string', 'max:100'],
            'factor_type' => ['required', new Enum(FactorType::class)],
            'description' => ['nullable', 'string'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rule_config' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
