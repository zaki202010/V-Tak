<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'order_no', 'tracking_token_hash', 'user_id', 'product_id', 'product_variant_id', 'product_name',
        'variant_name', 'target_user_id', 'target_server_id', 'unit_price', 'service_fee', 'discount', 'total',
        'payment_method_id', 'payment_method_name', 'promo_code', 'payment_status', 'topup_status',
        'provider_ref', 'idempotency_key', 'admin_note', 'expires_at', 'paid_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'paid_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function logs()
    {
        return $this->hasMany(TransactionLog::class)->orderBy('id');
    }

    public function log(string $event, ?string $message = null, array $meta = []): void
    {
        TransactionLog::create([
            'order_id' => $this->id, 'event' => $event, 'message' => $message,
            'meta' => $meta ?: null, 'created_at' => now(),
        ]);
    }

    public function tokenMatches(?string $token): bool
    {
        return $token && hash_equals($this->tracking_token_hash, hash('sha256', $token));
    }

    /** Pesanan menunggu pembayaran yang lewat batas waktu ditandai kedaluwarsa. */
    public static function expireStale(): void
    {
        $stale = static::where('payment_status', 'pending')->where('expires_at', '<', now())->get();
        foreach ($stale as $order) {
            DB::transaction(function () use ($order) {
                $o = static::lockForUpdate()->find($order->id);
                if (! $o || $o->payment_status !== 'pending') {
                    return;
                }
                $o->update(['payment_status' => 'expired']);
                $o->payment()?->update(['status' => 'expired']);
                if ($o->promo_code) {
                    Promotion::where('code', $o->promo_code)->where('used_count', '>', 0)->decrement('used_count');
                }
                $o->log('expired', 'Pesanan kedaluwarsa karena pembayaran tidak diterima tepat waktu.');
            });
        }
    }

    /** Data yang aman ditampilkan ke pelanggan. */
    public function toPublic(): array
    {
        return [
            'order_no' => $this->order_no,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'target_user_id' => $this->target_user_id,
            'target_server_id' => $this->target_server_id,
            'unit_price' => $this->unit_price,
            'service_fee' => $this->service_fee,
            'discount' => $this->discount,
            'total' => $this->total,
            'promo_code' => $this->promo_code,
            'payment_method_name' => $this->payment_method_name,
            'payment_status' => $this->payment_status,
            'topup_status' => $this->topup_status,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
        ];
    }
}
