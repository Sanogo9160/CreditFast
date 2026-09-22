<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'credit_request_id',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'duration_months',
        'annual_interest_rate_percent',
        'monthly_payment',
        'disbursed_at',
        'maturity_date',
        'outstanding_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'duration_months' => 'integer',
            'annual_interest_rate_percent' => 'decimal:2',
            'monthly_payment' => 'decimal:2',
            'disbursed_at' => 'date',
            'maturity_date' => 'date',
            'outstanding_amount' => 'decimal:2',
            'status' => LoanStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)->orderBy('due_date')->orderBy('id');
    }

    /**
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    #[Scope]
    protected function forClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    #[Scope]
    protected function approved(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Approved);
    }
}
