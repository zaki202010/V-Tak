<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'provider_sku', 'price', 'original_price', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'price' => 'integer', 'original_price' => 'integer'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
