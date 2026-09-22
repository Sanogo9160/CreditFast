<?php

namespace App\Models;

use App\Enums\ScoringRecommendation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_request_id',
        'scoring_model_id',
        'declared_income',
        'documented_income',
        'income_consistency_score',
        'expense_score',
        'activity_score',
        'activity_vitality_score',
        'document_score',
        'savings_score',
        'credit_history_score',
        'guarantee_score',
        'repayment_capacity_score',
        'residential_zone_score',
        'overall_score',
        'proposed_annual_interest_rate',
        'confidence_score',
        'recommendation',
        'analysis_summary',
    ];

    protected function casts(): array
    {
        return [
            'declared_income' => 'decimal:2',
            'documented_income' => 'decimal:2',
            'income_consistency_score' => 'decimal:2',
            'expense_score' => 'decimal:2',
            'activity_score' => 'decimal:2',
            'activity_vitality_score' => 'decimal:2',
            'document_score' => 'decimal:2',
            'savings_score' => 'decimal:2',
            'credit_history_score' => 'decimal:2',
            'guarantee_score' => 'decimal:2',
            'repayment_capacity_score' => 'decimal:2',
            'residential_zone_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'proposed_annual_interest_rate' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'recommendation' => ScoringRecommendation::class,
        ];
    }

    public function creditRequest(): BelongsTo
    {
        return $this->belongsTo(CreditRequest::class);
    }

    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class);
    }

    public function factors(): HasMany
    {
        return $this->hasMany(CreditScoreFactor::class);
    }
}
