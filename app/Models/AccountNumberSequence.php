<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountNumberSequence extends Model
{
    protected $fillable = [
        'caisse_id',
        'year',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_sequence' => 'integer',
        ];
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }
}
