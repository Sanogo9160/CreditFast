<?php

namespace App\Http\Requests\Agent;

use App\Enums\FieldVisitType;
use App\Models\CreditRequest;
use App\Models\FieldVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFieldVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FieldVisit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_type' => ['required', new Enum(FieldVisitType::class)],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'purpose' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'visit_type' => 'type de visite',
            'scheduled_at' => 'date du rendez-vous',
            'location_label' => 'lieu',
            'purpose' => 'objectif de la visite',
        ];
    }

    public function creditRequest(): CreditRequest
    {
        /** @var CreditRequest $creditRequest */
        $creditRequest = $this->route('creditRequest');

        return $creditRequest;
    }
}
