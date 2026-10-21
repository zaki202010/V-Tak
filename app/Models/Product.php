<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'image', 'needs_user_id', 'needs_server_id',
        'user_id_label', 'server_id_label', 'user_id_hint', 'provider_code', 'is_popular', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'needs_user_id' => 'boolean', 'needs_server_id' => 'boolean',
        'is_popular' => 'boolean', 'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('price');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? '/storage/'.$this->image : null;
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'image_url' => $this->imageUrl(),
            'category' => $this->category ? ['name' => $this->category->name, 'slug' => $this->category->slug] : null,
            'min_price' => isset($this->min_price) ? (int) $this->min_price : null,
            'is_popular' => $this->is_popular,
        ];
    }
}
