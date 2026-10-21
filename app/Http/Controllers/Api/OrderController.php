<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function store(StoreOrderRequest $r, OrderService $svc)
    {
        [$order, $token] = $svc->create($r->validated(), $r->user());

        return $this->ok(['order_no' => $order->order_no, 'tracking_token' => $token], 'Pesanan dibuat.', 201);
    }

    public function track(Request $r)
    {
        $order = $this->authorizedOrder($r, (string) $r->query('order_no'), $r->query('token'));
        if (! $order) {
            return $this->fail('Pesanan tidak ditemukan atau verifikasi salah.', 404);
        }

        return $this->ok($this->payload($order));
    }

    public function mine(Request $r)
    {
        Order::expireStale();
        $p = Order::where('user_id', $r->user()->id)->orderByDesc('id')->paginate(10);

        return $this->paginated($p, fn (Order $o) => $o->toPublic());
    }

    /** Hanya aktif pada VTAK_MODE=simulation. Bukan pembayaran sungguhan. */
    public function simulatePay(Request $r, string $orderNo, OrderService $svc)
    {
        if (config('vtak.mode') !== 'simulation') {
            return $this->fail('Pembayaran simulasi dinonaktifkan.', 403);
        }
        $order = $this->authorizedOrder($r, $orderNo, $r->input('token'));
        if (! $order) {
            return $this->fail('Pesanan tidak ditemukan atau verifikasi salah.', 404);
        }
        $order = $svc->markPaid($order, 'simulation');

        return $this->ok($this->payload($order), '[SIMULASI] Pembayaran berhasil ditandai.');
    }

    private function authorizedOrder(Request $r, string $no, ?string $token): ?Order
    {
        Order::expireStale();
        $order = Order::where('order_no', $no)->first();
        if (! $order) {
            return null;
        }
        $owner = $r->user() && $order->user_id === $r->user()->id;

        return ($owner || $order->tokenMatches($token)) ? $order : null;
    }

    private function payload(Order $o): array
    {
        $d = $o->toPublic();
        $d['timeline'] = $o->logs()->get(['event', 'message', 'created_at']);

        return $d;
    }
}
