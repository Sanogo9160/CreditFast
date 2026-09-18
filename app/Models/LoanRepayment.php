<?php

namespace App\Models;

use App\Enums\LoanRepaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'due_date',
        'payment_date',
        'expected_amount',
        'paid_amount',
        'days_late',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'payment_date' => 'date',
            'expected_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'days_late' => 'integer',
            'status' => LoanRepaymentStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
