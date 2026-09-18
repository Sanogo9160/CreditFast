<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnomalyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anomaly_type' => $this->anomaly_type,
            'severity' => $this->severity?->value ?? $this->severity,
            'description' => $this->description,
            'detected_value' => $this->detected_value,
            'expected_value' => $this->expected_value,
            'status' => $this->status?->value ?? $this->status,
            'resolution_comment' => $this->resolution_comment,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
