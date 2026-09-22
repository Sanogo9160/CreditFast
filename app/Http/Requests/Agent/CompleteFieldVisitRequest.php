<?php

namespace App\Http\Requests\Agent;

use App\Enums\FieldVisitOutcome;
use App\Models\FieldVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CompleteFieldVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FieldVisit $fieldVisit */
        $fieldVisit = $this->route('fieldVisit');

        return $this->user()?->can('complete', $fieldVisit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', new Enum(FieldVisitOutcome::class)],
            'findings' => ['required', 'string', 'min:10', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:2000'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outcome' => 'conclusion de la visite',
            'findings' => 'constats terrain',
            'recommendations' => 'recommandations',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'findings.min' => 'Décrivez les constats de la visite (au moins quelques mots).',
        ];
    }
}
