<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Str;

class SimulationGateway implements PaymentGatewayContract
{
    public function createPayment(Order $order): array
    {
        return [
            'reference' => 'SIMPAY-'.strtoupper(Str::random(8)),
            'instructions' => 'MODE SIMULASI: tidak ada pembayaran sungguhan. Gunakan tombol "Bayar (Simulasi)" untuk menguji alur.',
            'simulated' => true,
        ];
    }
}
