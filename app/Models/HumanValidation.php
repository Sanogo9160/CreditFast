<?php

namespace App\Models;

use App\Enums\ValidationDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumanValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'document_id',
        'validator_id',
        'validation_type',
        'decision',
        'comment',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ValidationDecision::class,
            'validated_at' => 'datetime',
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
