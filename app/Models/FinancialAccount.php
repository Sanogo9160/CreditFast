<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'caisse_id',
        'guichet_id',
        'cash_desk_id',
        'bank_account_application_id',
        'account_number',
        'account_type',
        'balance',
        'opened_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'opened_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function guichet(): BelongsTo
    {
        return $this->belongsTo(Guichet::class);
    }

    public function cashDesk(): BelongsTo
    {
        return $this->belongsTo(CashDesk::class);
    }

    public function bankAccountApplication(): BelongsTo
    {
        return $this->belongsTo(BankAccountApplication::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'account_id');
    }

    public function savingsHistories(): HasMany
    {
        return $this->hasMany(SavingsHistory::class, 'account_id');
    }
}
