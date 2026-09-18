<?php

namespace App\Http\Requests\Committee;

use App\Enums\CommitteeDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('creditRequest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', new Enum(CommitteeDecision::class)],
            'approved_amount' => ['required_if:decision,APPROVED,AMENDED', 'nullable', 'numeric', 'min:10000'],
            'approved_duration_months' => ['required_if:decision,APPROVED,AMENDED', 'nullable', 'integer', 'min:1', 'max:60'],
            'comment' => ['required', 'string', 'min:5'],
        ];
    }
}
