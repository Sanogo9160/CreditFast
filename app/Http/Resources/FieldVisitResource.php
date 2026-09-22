<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_request_id' => $this->credit_request_id,
            'client_id' => $this->client_id,
            'agent_id' => $this->agent_id,
            'visit_type' => $this->visit_type?->value ?? $this->visit_type,
            'status' => $this->status?->value ?? $this->status,
            'outcome' => $this->outcome?->value ?? $this->outcome,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'location_label' => $this->location_label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'purpose' => $this->purpose,
            'findings' => $this->findings,
            'recommendations' => $this->recommendations,
            'cancel_reason' => $this->cancel_reason,
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'full_name' => $this->agent->full_name,
                'email' => $this->agent->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
