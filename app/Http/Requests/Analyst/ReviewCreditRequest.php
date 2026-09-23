<?php

namespace App\Http\Requests\Analyst;

use App\Enums\ComplementSubject;
use App\Enums\ScoringRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ReviewCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('creditRequest')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recommendation' => ['required', new Enum(ScoringRecommendation::class)],
            'comment' => ['required', 'string', 'min:5'],
            'next_step' => ['nullable', 'in:COMMITTEE,VERIFICATION_REQUIRED'],
            'subject' => ['required_if:next_step,VERIFICATION_REQUIRED', 'nullable', new Enum(ComplementSubject::class)],
            'detail' => ['required_if:next_step,VERIFICATION_REQUIRED', 'nullable', 'string', 'min:5'],
        ];
    }
}
