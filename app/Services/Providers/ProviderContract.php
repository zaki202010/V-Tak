<?php

namespace App\Services\Providers;

use App\Models\Order;

/**
 * Kontrak provider top up. Implementasi nyata (Digiflazz, VIP Reseller, Apigames, dll.)
 * harus dibuat berdasarkan DOKUMENTASI RESMI provider setelah akses/API key tersedia.
 *
 * send()  mengembalikan: ['status' => 'success'|'pending'|'failed', 'ref' => string|null, 'message' => string]
 * check() mengembalikan struktur yang sama untuk transaksi yang sudah dikirim.
 */
interface ProviderContract
{
    public function send(Order $order): array;

    public function check(Order $order): array;
}
