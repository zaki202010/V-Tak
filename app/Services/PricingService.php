<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class PricingService
{
    /** Semua harga dihitung di server. Nilai dari browser tidak dipercaya. */
    public function quote(ProductVariant $variant, PaymentMethod $method, ?Promotion $promo = null, bool $promoRequested = false): array
    {
        $price = (int) $variant->price;
        $discount = 0;

        if ($promoRequested && ! $promo) {
            throw ValidationException::withMessages(['promo_code' => 'Kode promo tidak ditemukan.']);
        }
        if ($promo) {
            $discount = $this->discountFor($promo, $price);
        }

        $base = $price - $discount;
        $fee = (int) $method->fee_flat + (int) ceil($base * ((float) $method->fee_percent) / 100);

        return [
            'unit_price' => $price,
            'discount' => $discount,
            'service_fee' => $fee,
            'total' => $base + $fee,
            'promo_code' => $promo?->code,
        ];
    }

    private function discountFor(Promotion $p, int $price): int
    {
        $err = fn (string $m) => throw ValidationException::withMessages(['promo_code' => $m]);

        if (! $p->is_active) {
            $err('Kode promo tidak aktif.');
        }
        if ($p->starts_at && now()->lt($p->starts_at)) {
            $err('Kode promo belum berlaku.');
        }
        if ($p->ends_at && now()->gt($p->ends_at)) {
            $err('Kode promo sudah berakhir.');
        }
        if ($p->quota !== null && $p->used_count >= $p->quota) {
            $err('Kuota kode promo sudah habis.');
        }
        if ($price < $p->min_purchase) {
            $err('Minimal pembelian untuk promo ini Rp'.number_format($p->min_purchase, 0, ',', '.').'.');
        }

        $d = $p->type === 'percent' ? (int) floor($price * $p->value / 100) : (int) $p->value;
        if ($p->max_discount !== null) {
            $d = min($d, (int) $p->max_discount);
        }

        return max(0, min($d, $price));
    }
}
