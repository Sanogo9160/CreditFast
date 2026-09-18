<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsHistory extends Model
{
    use HasFactory;

    protected $table = 'savings_history';

    protected $fillable = [
        'client_id',
        'account_id',
        'period_start',
        'period_end',
        'total_deposits',
        'total_withdrawals',
        'deposit_count',
        'withdrawal_count',
        'average_balance',
        'closing_balance',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_deposits' => 'decimal:2',
            'total_withdrawals' => 'decimal:2',
            'deposit_count' => 'integer',
            'withdrawal_count' => 'integer',
            'average_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }
}
