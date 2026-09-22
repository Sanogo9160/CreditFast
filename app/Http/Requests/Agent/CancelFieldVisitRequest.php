<?php

namespace App\Http\Requests\Agent;

use App\Models\FieldVisit;
use Illuminate\Foundation\Http\FormRequest;

class CancelFieldVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FieldVisit $fieldVisit */
        $fieldVisit = $this->route('fieldVisit');

        return $this->user()?->can('cancel', $fieldVisit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'as_no_show' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'motif',
            'as_no_show' => 'client absent',
        ];
    }
}
