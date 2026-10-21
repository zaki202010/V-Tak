<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VtakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function newOrder(array $extra = [])
    {
        return $this->postJson('/api/orders', $extra + [
            'variant_id' => 1, 'payment_method' => 'qris', 'target_user_id' => '123456789',
        ]);
    }

    public function test_katalog_menampilkan_produk_aktif(): void
    {
        $this->getJson('/api/products')->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonPath('data.items.0.name', 'Roblox');
    }

    public function test_harga_dihitung_server(): void
    {
        // 15.000 + ceil(15.000 * 0,70%) = 15.105
        $this->postJson('/api/quote', ['variant_id' => 1, 'payment_method' => 'qris'])
            ->assertOk()->assertJsonPath('data.total', 15105);
    }

    public function test_promo_menghitung_diskon_dan_batas_maksimal(): void
    {
        // 10% dari 150.000 = 15.000, dibatasi maks 10.000
        $this->postJson('/api/quote', ['variant_id' => 3, 'payment_method' => 'qris', 'promo_code' => 'DEMO10'])
            ->assertOk()->assertJsonPath('data.discount', 10000);
    }

    public function test_user_id_wajib_diisi(): void
    {
        $this->postJson('/api/orders', ['variant_id' => 1, 'payment_method' => 'qris'])->assertStatus(422);
    }

    public function test_server_id_wajib_untuk_mobile_legends(): void
    {
        // variant 9 = Mobile Legends 86 Diamond
        $this->postJson('/api/orders', ['variant_id' => 9, 'payment_method' => 'qris', 'target_user_id' => '12345678'])
            ->assertStatus(422)->assertJsonValidationErrors('target_server_id');
    }

    public function test_pesanan_idempoten_dan_nomor_unik(): void
    {
        $a = $this->newOrder(['idempotency_key' => 'abc-123'])->assertCreated();
        $b = $this->newOrder(['idempotency_key' => 'abc-123'])->assertCreated();
        $this->assertSame($a->json('data.order_no'), $b->json('data.order_no'));
        $this->assertSame(1, Order::count());
        $c = $this->newOrder(['idempotency_key' => 'xyz-999'])->assertCreated();
        $this->assertNotSame($a->json('data.order_no'), $c->json('data.order_no'));
    }

    public function test_tracking_butuh_token(): void
    {
        $o = $this->newOrder()->json('data');
        $this->getJson('/api/orders/track?order_no='.$o['order_no'])->assertNotFound();
        $this->getJson('/api/orders/track?order_no='.$o['order_no'].'&token=salah')->assertNotFound();
        $this->getJson('/api/orders/track?order_no='.$o['order_no'].'&token='.$o['tracking_token'])->assertOk()
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_pelanggan_lain_tidak_bisa_membaca_pesanan(): void
    {
        $o = $this->newOrder()->json('data');
        $this->actingAs(User::where('email', 'demo@vtak.test')->first())
            ->getJson('/api/orders/track?order_no='.$o['order_no'])->assertNotFound();
    }

    public function test_alur_bayar_simulasi_berhasil_dan_idempoten(): void
    {
        $o = $this->newOrder()->json('data');
        $this->postJson('/api/orders/'.$o['order_no'].'/simulate-pay', ['token' => $o['tracking_token']])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.topup_status', 'success');
        $this->postJson('/api/orders/'.$o['order_no'].'/simulate-pay', ['token' => $o['tracking_token']])->assertOk();
        $this->assertSame(1, Order::where('payment_status', 'paid')->count());
    }

    public function test_topup_gagal_masuk_pemeriksaan_manual(): void
    {
        $o = $this->newOrder(['target_user_id' => '999000'])->json('data');
        $this->postJson('/api/orders/'.$o['order_no'].'/simulate-pay', ['token' => $o['tracking_token']])
            ->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.topup_status', 'manual_review');
    }

    public function test_endpoint_admin_terlindungi(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
        $this->actingAs(User::where('email', 'demo@vtak.test')->first())->getJson('/api/admin/dashboard')->assertStatus(403);
        $this->actingAs(User::where('email', 'admin@vtak.test')->first())->getJson('/api/admin/dashboard')->assertOk();
    }

    public function test_login_salah_ditolak(): void
    {
        $this->postJson('/api/login', ['email' => 'demo@vtak.test', 'password' => 'salah'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'demo@vtak.test', 'password' => 'Demo12345!'])->assertOk();
    }
}
