<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'monthly_income',
        'other_income',
        'monthly_expenses',
        'existing_debt_payment',
        'dependents_count',
        'disposable_income',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'other_income' => 'decimal:2',
            'monthly_expenses' => 'decimal:2',
            'existing_debt_payment' => 'decimal:2',
            'dependents_count' => 'integer',
            'disposable_income' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
