<?php

namespace App\Models;

use App\Enums\ScoringRecommendation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'analyst_id',
        'review_status',
        'recommendation',
        'comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'recommendation' => ScoringRecommendation::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id');
    }
}
