<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderAdminController extends ApiController
{
    public function index(Request $r)
    {
        Order::expireStale();
        $q = Order::query();
        if ($s = trim((string) $r->query('q'))) {
            $q->where('order_no', 'like', '%'.addcslashes($s, '%_\\').'%');
        }
        if ($v = $r->query('payment_status')) {
            $q->where('payment_status', $v);
        }
        if ($v = $r->query('topup_status')) {
            $q->where('topup_status', $v);
        }
        if ($v = $r->query('from')) {
            $q->whereDate('created_at', '>=', $v);
        }
        if ($v = $r->query('to')) {
            $q->whereDate('created_at', '<=', $v);
        }

        return $this->paginated($q->orderByDesc('id')->paginate(15), fn (Order $o) => $o->toPublic() + ['id' => $o->id]);
    }

    public function show(Order $order)
    {
        $order->load('user:id,name,email,phone', 'logs');

        return $this->ok($order->toPublic() + [
            'id' => $order->id, 'provider_ref' => $order->provider_ref, 'admin_note' => $order->admin_note,
            'customer' => $order->user, 'logs' => $order->logs,
        ]);
    }

    /** Catatan admin + penandaan manual untuk transaksi lunas yang bermasalah. Tidak dapat menandai pembayaran sebagai lunas. */
    public function update(Request $r, Order $order)
    {
        $d = $r->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'topup_status' => ['nullable', 'in:manual_review,failed'],
        ]);
        if (! empty($d['topup_status'])) {
            if ($order->payment_status !== 'paid' || $order->topup_status === 'success') {
                return $this->fail('Status top up hanya dapat diubah untuk pesanan lunas yang belum berhasil.', 422);
            }
            $order->topup_status = $d['topup_status'];
            $order->log('admin_status', 'Admin mengubah status top up menjadi '.$d['topup_status'].'.');
        }
        $order->admin_note = $d['admin_note'] ?? $order->admin_note;
        $order->save();
        AdminActivityLog::record($r->user(), 'order.update', $order->order_no, $d);

        return $this->ok(null, 'Pesanan diperbarui.');
    }

    public function retry(Request $r, Order $order, OrderService $svc)
    {
        if ($order->payment_status !== 'paid' || ! in_array($order->topup_status, ['failed', 'manual_review'], true)) {
            return $this->fail('Pemrosesan ulang hanya untuk pesanan lunas dengan top up gagal / perlu pemeriksaan.', 422);
        }
        $order->log('admin_retry', 'Admin memproses ulang top up.');
        $o = $svc->processTopup($order, true);
        AdminActivityLog::record($r->user(), 'order.retry', $order->order_no);

        return $this->ok(['topup_status' => $o->topup_status], 'Top up diproses ulang.');
    }
}
