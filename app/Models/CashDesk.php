<?php

namespace App\Models;

use Database\Factories\CashDeskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDesk extends Model
{
    /** @use HasFactory<CashDeskFactory> */
    use HasFactory;

    protected $fillable = [
        'guichet_id',
        'code',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function guichet(): BelongsTo
    {
        return $this->belongsTo(Guichet::class);
    }
}
