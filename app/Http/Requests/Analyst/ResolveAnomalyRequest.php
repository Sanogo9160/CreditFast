<?php

namespace App\Http\Requests\Analyst;

use App\Enums\AnomalyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ResolveAnomalyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resolve', $this->route('anomaly')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(AnomalyStatus::class)],
            'resolution_comment' => ['required', 'string', 'min:5'],
        ];
    }
}
