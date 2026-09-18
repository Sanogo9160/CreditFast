<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditScoreFactorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'factor_name' => $this->factor_name,
            'factor_type' => $this->factor_type?->value ?? $this->factor_type,
            'score' => (float) $this->score,
            'weight' => (float) $this->weight,
            'explanation' => $this->explanation,
        ];
    }
}
