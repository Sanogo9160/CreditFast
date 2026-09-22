<?php

namespace App\Models;

use Database\Factories\GuichetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guichet extends Model
{
    /** @use HasFactory<GuichetFactory> */
    use HasFactory;

    protected $fillable = [
        'caisse_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function cashDesks(): HasMany
    {
        return $this->hasMany(CashDesk::class);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('cashDesks', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('caisse', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isSelectable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->relationLoaded('caisse') && $this->caisse && ! $this->caisse->is_active) {
            return false;
        }

        if ($this->relationLoaded('cashDesks')) {
            return $this->cashDesks->contains(fn (CashDesk $desk) => $desk->is_active);
        }

        return $this->cashDesks()->where('is_active', true)->exists();
    }

    public function firstActiveCashDesk(): ?CashDesk
    {
        return $this->cashDesks()->where('is_active', true)->orderBy('id')->first();
    }
}
