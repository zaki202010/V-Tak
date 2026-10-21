<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'type', 'fee_flat', 'fee_percent', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'fee_flat' => 'integer', 'fee_percent' => 'float'];
}
