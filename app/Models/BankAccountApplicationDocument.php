<?php

namespace App\Models;

use App\Enums\BankAccountDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountApplicationDocument extends Model
{
    protected $fillable = [
        'bank_account_application_id',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => BankAccountDocumentType::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(BankAccountApplication::class, 'bank_account_application_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
