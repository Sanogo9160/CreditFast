<?php

namespace App\Http\Requests\Committee;

use App\Enums\CommitteeDecision;
use App\Enums\ComplementSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('creditRequest')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', new Enum(CommitteeDecision::class)],
            'approved_amount' => ['required_if:decision,APPROVED,AMENDED', 'nullable', 'numeric', 'min:10000'],
            'approved_duration_months' => ['required_if:decision,APPROVED,AMENDED', 'nullable', 'integer', 'min:1', 'max:60'],
            'comment' => ['nullable', 'string', 'min:5'],
            'reason' => ['required_if:decision,ADJOURNED,VERIFICATION_REQUIRED', 'nullable', 'string', 'min:5'],
            'what' => ['required_if:decision,ADJOURNED,VERIFICATION_REQUIRED', 'nullable', 'string', 'min:5'],
            'subject' => ['required_if:decision,VERIFICATION_REQUIRED', 'nullable', new Enum(ComplementSubject::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'approved_amount' => 'montant approuvé',
            'approved_duration_months' => 'durée approuvée',
            'reason' => 'pourquoi',
            'what' => 'quoi',
            'subject' => 'sujet du complément',
        ];
    }
}
