<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Kontrak payment gateway (Midtrans / Xendit / Tripay). Implementasi nyata harus mengikuti
 * dokumentasi resmi gateway dan memverifikasi callback dengan mekanisme resmi gateway.
 *
 * createPayment() mengembalikan: ['reference' => string|null, 'instructions' => string, 'simulated' => bool]
 */
interface PaymentGatewayContract
{
    public function createPayment(Order $order): array;
}
