<?php

namespace App\Models;

use App\Enums\CreditRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'credit_status_history';

    protected $fillable = [
        'credit_request_id',
        'changed_by',
        'old_status',
        'new_status',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'old_status' => CreditRequestStatus::class,
            'new_status' => CreditRequestStatus::class,
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
