<?php

namespace App\Models;

use App\Enums\FieldVisitOutcome;
use App\Enums\FieldVisitStatus;
use App\Enums\FieldVisitType;
use Database\Factories\FieldVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldVisit extends Model
{
    /** @use HasFactory<FieldVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'client_id',
        'agent_id',
        'visit_type',
        'status',
        'outcome',
        'scheduled_at',
        'started_at',
        'completed_at',
        'location_label',
        'latitude',
        'longitude',
        'purpose',
        'findings',
        'recommendations',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'visit_type' => FieldVisitType::class,
            'status' => FieldVisitStatus::class,
            'outcome' => FieldVisitOutcome::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            FieldVisitStatus::Scheduled,
            FieldVisitStatus::InProgress,
        ], true);
    }
}
