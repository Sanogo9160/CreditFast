<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScoringModelStatus;
use App\Models\ScoringModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateScoringModelStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(ScoringModelStatus::class)],
        ];
    }
}
