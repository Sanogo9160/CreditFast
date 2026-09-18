<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'extracted_text',
        'extraction_status',
        'extraction_confidence',
        'extracted_data',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'extraction_confidence' => 'decimal:2',
            'extracted_data' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
