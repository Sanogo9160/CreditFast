<?php

namespace App\Models;

use Database\Factories\CaisseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caisse extends Model
{
    /** @use HasFactory<CaisseFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function guichets(): HasMany
    {
        return $this->hasMany(Guichet::class);
    }
}
