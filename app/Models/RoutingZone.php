<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutingZone extends Model
{
    protected $fillable = [
        'agency_code',
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
}
