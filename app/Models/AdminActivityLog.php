<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'target', 'detail', 'ip'];

    protected $casts = ['detail' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?User $user, string $action, ?string $target = null, array $detail = []): void
    {
        static::create([
            'user_id' => $user?->id, 'action' => $action, 'target' => $target,
            'detail' => $detail ?: null, 'ip' => request()->ip(),
        ]);
    }
}
