<?php

namespace App\Http\Requests\Profile;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity|null $activity */
        $activity = $this->route('activity');

        return $this->user()?->client !== null
            && $activity instanceof Activity
            && $activity->client_id === $this->user()->client->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_type' => ['sometimes', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:255'],
            'monthly_revenue' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
