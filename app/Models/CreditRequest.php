<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Enums\CreditRequestStatus;
use App\Enums\RepaymentCapacityStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreditRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'borrower_type',
        'credit_type',
        'activity_id',
        'requested_amount',
        'duration_months',
        'purpose',
        'declared_monthly_income',
        'declared_monthly_expenses',
        'estimated_monthly_payment',
        'disposable_income',
        'repayment_capacity_status',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'borrower_type' => ClientType::class,
            'credit_type' => CreditProductType::class,
            'requested_amount' => 'decimal:2',
            'duration_months' => 'integer',
            'declared_monthly_income' => 'decimal:2',
            'declared_monthly_expenses' => 'decimal:2',
            'estimated_monthly_payment' => 'decimal:2',
            'disposable_income' => 'decimal:2',
            'repayment_capacity_status' => RepaymentCapacityStatus::class,
            'status' => CreditRequestStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function guarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(CreditAnalysis::class)->latestOfMany();
    }

    public function creditAnalyses(): HasMany
    {
        return $this->hasMany(CreditAnalysis::class);
    }

    public function humanValidations(): HasMany
    {
        return $this->hasMany(HumanValidation::class);
    }

    public function creditReviews(): HasMany
    {
        return $this->hasMany(CreditReview::class);
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class);
    }

    public function committeeDecisions(): HasMany
    {
        return $this->hasMany(CreditCommitteeDecision::class);
    }

    public function latestCommitteeDecision(): HasOne
    {
        return $this->hasOne(CreditCommitteeDecision::class)->latestOfMany();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CreditStatusHistory::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    /**
     * @param  Builder<CreditRequest>  $query
     * @return Builder<CreditRequest>
     */
    #[Scope]
    protected function forClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * @param  Builder<CreditRequest>  $query
     * @return Builder<CreditRequest>
     */
    #[Scope]
    protected function inAnalystQueue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CreditRequestStatus::Submitted,
            CreditRequestStatus::Analysis,
            CreditRequestStatus::VerificationRequired,
            CreditRequestStatus::CreditReview,
        ]);
    }

    /**
     * @param  Builder<CreditRequest>  $query
     * @return Builder<CreditRequest>
     */
    #[Scope]
    protected function inCommitteeQueue(Builder $query): Builder
    {
        return $query->where('status', CreditRequestStatus::Committee);
    }
}
