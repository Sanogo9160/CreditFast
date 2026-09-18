<?php

namespace App\Http\Requests\Analyst;

use App\Enums\ScoringRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ReviewCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('creditRequest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'recommendation' => ['required', new Enum(ScoringRecommendation::class)],
            'comment' => ['required', 'string', 'min:5'],
            'next_step' => ['nullable', 'in:COMMITTEE,VERIFICATION_REQUIRED'],
        ];
    }
}
