<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['title', 'subtitle', 'link_url', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
