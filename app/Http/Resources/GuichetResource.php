<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuichetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caisse_id' => $this->caisse_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'is_selectable' => $this->when(
                $this->relationLoaded('cashDesks'),
                fn () => $this->isSelectable()
            ),
            'cash_desks' => CashDeskResource::collection($this->whenLoaded('cashDesks')),
        ];
    }
}
