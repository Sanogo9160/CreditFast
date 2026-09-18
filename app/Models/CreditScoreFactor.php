<?php

namespace App\Models;

use App\Enums\FactorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditScoreFactor extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_analysis_id',
        'scoring_rule_id',
        'factor_name',
        'factor_type',
        'score',
        'weight',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'factor_type' => FactorType::class,
            'score' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    public function creditAnalysis(): BelongsTo
    {
        return $this->belongsTo(CreditAnalysis::class);
    }

    public function scoringRule(): BelongsTo
    {
        return $this->belongsTo(ScoringRule::class);
    }
}
