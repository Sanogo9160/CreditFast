<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuaranteeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_request_id' => $this->credit_request_id,
            'guarantee_type' => $this->guarantee_type,
            'description' => $this->description,
            'declared_value' => $this->declared_value,
            'verified_value' => $this->verified_value,
            'verification_status' => $this->verification_status?->value ?? $this->verification_status,
            'has_file' => filled($this->file_path),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_url' => $this->file_path ? route('guarantees.file', $this->resource, true) : null,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
