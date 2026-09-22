<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountApplicationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type?->value ?? $this->document_type,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'download_url' => url("/api/bank-account-applications/documents/{$this->id}/file"),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
