<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * TEMPLATE webhook pembayaran generik (HMAC-SHA256 atas body mentah, header X-VTAK-Signature).
 * Untuk gateway sungguhan (Midtrans/Xendit/Tripay) ganti verifikasi dengan mekanisme RESMI gateway tersebut.
 */
class WebhookController extends ApiController
{
    public function payment(Request $r, OrderService $svc)
    {
        $secret = (string) config('vtak.webhook_secret');
        if ($secret === '') {
            return $this->fail('Webhook belum dikonfigurasi.', 503);
        }
        $expected = hash_hmac('sha256', $r->getContent(), $secret);
        if (! hash_equals($expected, (string) $r->header('X-VTAK-Signature', ''))) {
            return $this->fail('Signature tidak valid.', 401);
        }

        $order = Order::where('order_no', (string) $r->input('order_no'))->first();
        if (! $order) {
            return $this->fail('Pesanan tidak ditemukan.', 404);
        }
        if ((int) $r->input('amount') !== (int) $order->total) {
            return $this->fail('Nominal tidak sesuai.', 422);
        }

        if ($r->input('status') === 'paid') {
            $svc->markPaid($order, 'webhook', ['webhook' => true]);
        } elseif ($r->input('status') === 'failed' && $order->payment_status === 'pending') {
            $order->update(['payment_status' => 'failed']);
            $order->payment()->update(['status' => 'failed']);
            $order->log('payment_failed', 'Pembayaran gagal (webhook).');
        }

        return $this->ok(null, 'Diterima.');
    }
}
