<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'original_filename' => $this->original_filename,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at?->toIso8601String(),
            'extraction' => $this->whenLoaded('extraction'),
        ];
    }
}
