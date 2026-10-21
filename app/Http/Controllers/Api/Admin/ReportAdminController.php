<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Definisi (dipakai konsisten di dashboard & laporan):
 *  - Berhasil : topup_status = success
 *  - Gagal    : payment_status = failed ATAU topup_status IN (failed, manual_review)
 *  - Menunggu : payment_status = pending
 *  - Penjualan: SUM(total) pesanan Berhasil
 */
class ReportAdminController extends ApiController
{
    public function dashboard()
    {
        Order::expireStale();
        $s = $this->summary();
        $s['today_orders'] = Order::whereDate('created_at', today())->count();

        $days = collect(range(6, 0))->map(fn ($i) => today()->subDays($i)->toDateString());
        $sales = Order::where('topup_status', 'success')->whereDate('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) d, SUM(total) t')->groupBy('d')->pluck('t', 'd');
        $s['chart'] = $days->map(fn ($d) => ['date' => $d, 'total' => (int) ($sales[$d] ?? 0)])->values();

        return $this->ok($s);
    }

    public function report(Request $r)
    {
        Order::expireStale();
        [$from, $to] = $this->range($r);
        $s = $this->summary($from, $to);

        $rows = Order::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw("DATE(created_at) d, COUNT(*) c, SUM(total) v, SUM(topup_status = 'success') ok_count, SUM(CASE WHEN topup_status = 'success' THEN total ELSE 0 END) rev")
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($x) => ['date' => $x->d, 'orders' => (int) $x->c, 'order_value' => (int) $x->v, 'success' => (int) $x->ok_count, 'revenue' => (int) $x->rev]);

        return $this->ok($s + ['from' => $from, 'to' => $to, 'daily' => $rows]);
    }

    public function export(Request $r)
    {
        [$from, $to] = $this->range($r);
        $name = "laporan-vtak-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['No Pesanan', 'Tanggal', 'Produk', 'Nominal', 'Total', 'Metode', 'Status Bayar', 'Status Top Up']);
            Order::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->orderBy('id')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $o) {
                        // Cegah formula injection di Excel
                        $safe = fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'".$v : $v;
                        fputcsv($out, [$o->order_no, $o->created_at->format('Y-m-d H:i'), $safe($o->product_name), $safe($o->variant_name),
                            $o->total, $o->payment_method_name, $o->payment_status, $o->topup_status]);
                    }
                });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $r): array
    {
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [$r->query('from') ?: today()->subDays(29)->toDateString(), $r->query('to') ?: today()->toDateString()];
    }

    private function summary(?string $from = null, ?string $to = null): array
    {
        $q = fn () => Order::query()
            ->when($from, fn ($x) => $x->whereDate('created_at', '>=', $from))
            ->when($to, fn ($x) => $x->whereDate('created_at', '<=', $to));

        return [
            'total_orders' => $q()->count(),
            'total_order_value' => (int) $q()->sum('total'),
            'pending' => $q()->where('payment_status', 'pending')->count(),
            'success' => $q()->where('topup_status', 'success')->count(),
            'failed' => $q()->where(fn ($w) => $w->where('payment_status', 'failed')->orWhereIn('topup_status', ['failed', 'manual_review']))->count(),
            'expired' => $q()->where('payment_status', 'expired')->count(),
            'revenue' => (int) $q()->where('topup_status', 'success')->sum('total'),
        ];
    }
}
