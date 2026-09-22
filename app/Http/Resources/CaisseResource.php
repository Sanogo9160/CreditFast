<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaisseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'is_active' => $this->is_active,
            'guichets' => GuichetResource::collection($this->whenLoaded('guichets')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
