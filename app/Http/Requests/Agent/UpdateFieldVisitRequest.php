<?php

namespace App\Http\Requests\Agent;

use App\Enums\FieldVisitType;
use App\Models\FieldVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFieldVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FieldVisit $fieldVisit */
        $fieldVisit = $this->route('fieldVisit');

        return $this->user()?->can('update', $fieldVisit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_type' => ['sometimes', new Enum(FieldVisitType::class)],
            'scheduled_at' => ['sometimes', 'date', 'after_or_equal:now'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
