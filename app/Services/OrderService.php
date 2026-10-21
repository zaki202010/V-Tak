<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayContract;
use App\Services\Providers\ProviderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private PricingService $pricing,
        private ProviderContract $provider,
        private PaymentGatewayContract $gateway,
    ) {}

    /** @return array{0: Order, 1: string} [order, tracking token polos] */
    public function create(array $d, ?User $user): array
    {
        $token = Str::random(32);

        // Idempotensi: request ulang dengan kunci sama mengembalikan pesanan yang sama.
        if (! empty($d['idempotency_key'])) {
            $existing = Order::where('idempotency_key', $d['idempotency_key'])->first();
            if ($existing) {
                $existing->update(['tracking_token_hash' => hash('sha256', $token)]);

                return [$existing, $token];
            }
        }

        $variant = ProductVariant::with('product')->where('is_active', true)->find($d['variant_id']);
        if (! $variant || ! $variant->product->is_active) {
            throw ValidationException::withMessages(['variant_id' => 'Nominal tidak tersedia.']);
        }
        $method = PaymentMethod::where('is_active', true)->where('code', $d['payment_method'])->first();
        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak tersedia.']);
        }
        $this->validateTarget($variant->product, $d);

        $order = DB::transaction(function () use ($d, $variant, $method, $user, $token) {
            $promo = null;
            $code = strtoupper(trim((string) ($d['promo_code'] ?? '')));
            if ($code !== '') {
                $promo = Promotion::where('code', $code)->lockForUpdate()->first();
            }
            $q = $this->pricing->quote($variant, $method, $promo, $code !== '');

            $p = $variant->product;
            $order = Order::create([
                'order_no' => $this->newOrderNo(),
                'tracking_token_hash' => hash('sha256', $token),
                'user_id' => $user?->id,
                'product_id' => $p->id,
                'product_variant_id' => $variant->id,
                'product_name' => $p->name,
                'variant_name' => $variant->name,
                'target_user_id' => $p->needs_user_id ? trim($d['target_user_id']) : null,
                'target_server_id' => $p->needs_server_id ? trim($d['target_server_id']) : null,
                'unit_price' => $q['unit_price'],
                'service_fee' => $q['service_fee'],
                'discount' => $q['discount'],
                'total' => $q['total'],
                'payment_method_id' => $method->id,
                'payment_method_name' => $method->name,
                'promo_code' => $q['promo_code'],
                'payment_status' => 'pending',
                'topup_status' => 'waiting',
                'idempotency_key' => $d['idempotency_key'] ?? null,
                'expires_at' => now()->addMinutes((int) Setting::get('order_expiry_minutes', 60)),
            ]);

            $gw = $this->gateway->createPayment($order);
            Payment::create([
                'order_id' => $order->id, 'method' => $method->code, 'amount' => $order->total,
                'status' => 'pending', 'gateway_ref' => $gw['reference'], 'payload' => ['simulated' => $gw['simulated']],
            ]);

            if ($promo) {
                $promo->increment('used_count');
                PromotionUsage::create(['promotion_id' => $promo->id, 'order_id' => $order->id, 'user_id' => $user?->id]);
            }
            $order->log('created', 'Pesanan dibuat. Menunggu pembayaran.');

            return $order;
        });

        return [$order, $token];
    }

    private function validateTarget(Product $p, array $d): void
    {
        $errors = [];
        if ($p->needs_user_id) {
            $v = trim((string) ($d['target_user_id'] ?? ''));
            if ($v === '') {
                $errors['target_user_id'] = $p->user_id_label.' wajib diisi.';
            } elseif (! preg_match('/^[A-Za-z0-9_\-\.@]{3,50}$/', $v)) {
                $errors['target_user_id'] = $p->user_id_label.' tidak valid (3-50 karakter: huruf, angka, _ - . @).';
            }
        }
        if ($p->needs_server_id) {
            $v = trim((string) ($d['target_server_id'] ?? ''));
            if ($v === '') {
                $errors['target_server_id'] = ($p->server_id_label ?: 'Server ID').' wajib diisi.';
            } elseif (! preg_match('/^[A-Za-z0-9\-]{1,20}$/', $v)) {
                $errors['target_server_id'] = ($p->server_id_label ?: 'Server ID').' tidak valid.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function newOrderNo(): string
    {
        do {
            $no = 'VTK'.now()->format('ymd').strtoupper(Str::random(6));
        } while (Order::where('order_no', $no)->exists());

        return $no;
    }

    /** Tandai lunas (idempoten). $source: simulation | webhook. */
    public function markPaid(Order $order, string $source, array $payload = []): Order
    {
        DB::transaction(function () use ($order, $source, $payload) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($o->payment_status === 'paid') {
                return; // sudah lunas: abaikan permintaan ganda
            }
            if ($o->payment_status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'Pesanan tidak dapat dibayar (status: '.$o->payment_status.').']);
            }
            if ($o->expires_at && now()->gt($o->expires_at)) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah melewati batas waktu pembayaran.']);
            }
            $o->update(['payment_status' => 'paid', 'paid_at' => now()]);
            $o->payment->update(['status' => 'paid', 'paid_at' => now(), 'payload' => $payload ?: ['source' => $source]]);
            $o->log('paid', $source === 'simulation' ? '[SIMULASI] Pembayaran ditandai lunas.' : 'Pembayaran diterima.', ['source' => $source]);
        });

        $fresh = $order->fresh();
        $this->processTopup($fresh);

        return $fresh->fresh();
    }

    /** Kirim top up ke provider. Hanya untuk pesanan lunas; aman dipanggil berulang. */
    public function processTopup(Order $order, bool $retry = false): Order
    {
        $claimed = DB::transaction(function () use ($order, $retry) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            $allowed = $retry ? ['waiting', 'failed', 'manual_review'] : ['waiting'];
            if ($o->payment_status !== 'paid' || ! in_array($o->topup_status, $allowed, true)) {
                return false;
            }
            $o->update(['topup_status' => 'processing']);
            $o->log('topup_processing', 'Top up diproses.');

            return true;
        });
        if (! $claimed) {
            return $order->fresh();
        }

        $o = $order->fresh();
        try {
            $res = $this->provider->send($o);
        } catch (\Throwable $e) {
            report($e);
            $res = ['status' => 'failed', 'ref' => null, 'message' => 'Provider tidak dapat dihubungi. Perlu pemeriksaan manual.'];
        }

        $map = ['success' => 'success', 'pending' => 'processing', 'failed' => 'manual_review'];
        $o->update(['topup_status' => $map[$res['status']] ?? 'manual_review', 'provider_ref' => $res['ref'] ?? null]);
        $o->log('topup_'.$res['status'], $res['message']);

        return $o->fresh();
    }
}
