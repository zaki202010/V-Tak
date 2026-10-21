<?php

namespace App\Services\Providers;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * PROVIDER SIMULASI — tidak mengirim apa pun ke provider sungguhan.
 * Aturan demo: jika User ID tujuan berakhiran "000", simulasi gagal (untuk menguji penanganan gagal).
 */
class SimulationProvider implements ProviderContract
{
    public function send(Order $order): array
    {
        if (str_ends_with((string) $order->target_user_id, '000')) {
            return ['status' => 'failed', 'ref' => null, 'message' => '[SIMULASI] Provider menolak transaksi (User ID berakhiran 000).'];
        }

        return ['status' => 'success', 'ref' => 'SIM-'.strtoupper(Str::random(10)), 'message' => '[SIMULASI] Top up dianggap berhasil. Tidak ada pengiriman ke provider sungguhan.'];
    }

    public function check(Order $order): array
    {
        return ['status' => $order->topup_status === 'success' ? 'success' : 'pending', 'ref' => $order->provider_ref, 'message' => '[SIMULASI] Pemeriksaan status.'];
    }
}
