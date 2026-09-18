<?php

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'document_id',
        'anomaly_type',
        'severity',
        'description',
        'detected_value',
        'expected_value',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_comment',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AnomalySeverity::class,
            'status' => AnomalyStatus::class,
            'resolved_at' => 'datetime',
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<Anomaly>  $query
     * @return Builder<Anomaly>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', AnomalyStatus::Open);
    }
}
