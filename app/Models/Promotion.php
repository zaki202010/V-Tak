<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 'min_purchase',
        'quota', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
