<?php

namespace App\Models;

use App\Enums\CommitteeDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCommitteeDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'committee_member_id',
        'decision',
        'approved_amount',
        'approved_duration_months',
        'annual_interest_rate_percent',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => CommitteeDecision::class,
            'approved_amount' => 'decimal:2',
            'approved_duration_months' => 'integer',
            'annual_interest_rate_percent' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committee_member_id');
    }
}
