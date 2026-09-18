<?php

namespace App\Models;

use App\Enums\ScoringMode;
use App\Enums\ScoringModelStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'scoring_mode',
        'description',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scoring_mode' => ScoringMode::class,
            'status' => ScoringModelStatus::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ScoringRule::class);
    }

    /**
     * @param  Builder<ScoringModel>  $query
     * @return Builder<ScoringModel>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->where('status', ScoringModelStatus::Active)
            ->where(function (Builder $builder): void {
                $builder->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
    }

    public function creditAnalyses(): HasMany
    {
        return $this->hasMany(CreditAnalysis::class);
    }
}
