<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAssignmentLog extends Model
{
    protected $fillable = [
        'credit_request_id',
        'assigned_by',
        'previous_agent_id',
        'new_agent_id',
        'reason',
    ];

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function previousAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_agent_id');
    }

    public function newAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_agent_id');
    }
}
