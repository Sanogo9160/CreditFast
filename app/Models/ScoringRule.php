<?php

namespace App\Models;

use App\Enums\FactorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'scoring_model_id',
        'rule_code',
        'rule_name',
        'factor_type',
        'description',
        'weight',
        'min_score',
        'max_score',
        'rule_config',
        'priority',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'factor_type' => FactorType::class,
            'weight' => 'decimal:2',
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'rule_config' => 'array',
            'priority' => 'integer',
        ];
    }

    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class);
    }
}
