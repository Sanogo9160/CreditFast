<?php

namespace App\Models;

use App\Enums\GuaranteeVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guarantee extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'guarantee_type',
        'description',
        'declared_value',
        'verified_value',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'declared_value' => 'decimal:2',
            'verified_value' => 'decimal:2',
            'verification_status' => GuaranteeVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
