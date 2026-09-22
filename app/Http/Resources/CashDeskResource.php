<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDeskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guichet_id' => $this->guichet_id,
            'code' => $this->code,
            'label' => $this->label,
            'is_active' => $this->is_active,
        ];
    }
}
