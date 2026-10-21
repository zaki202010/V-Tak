<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DATA DEMO / SIMULASI. Produk, harga, dan akun di bawah ini hanya untuk pengembangan.
 * Ganti kata sandi admin sebelum dipakai di luar komputer lokal.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = new User(['name' => 'Admin V-TAK', 'email' => 'admin@vtak.test', 'password' => 'Admin12345!']);
        $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();
        User::create(['name' => 'Pelanggan Demo', 'email' => 'demo@vtak.test', 'phone' => '081200000000', 'password' => 'Demo12345!']);

        foreach ([
            'site_name' => 'V-TAK', 'tagline' => 'Top up game & produk digital, cepat dan transparan.',
            'contact_wa' => '6281200000000', 'contact_email' => 'cs@vtak.test',
            'contact_hours' => 'Setiap hari, 08.00 - 22.00 WIB', 'order_expiry_minutes' => '60',
        ] as $k => $v) {
            Setting::put($k, $v);
        }

        $cats = [];
        foreach ([['Game', 'game', '🎮'], ['Pulsa', 'pulsa', '📱'], ['E-Wallet', 'ewallet', '👛'], ['Token Listrik', 'token-listrik', '⚡']] as $i => [$n, $s, $ic]) {
            $cats[$s] = Category::create(['name' => $n, 'slug' => $s, 'icon' => $ic, 'sort_order' => $i]);
        }

        $products = [
            ['game', 'Roblox', 'roblox', true, false, 'Username / User ID', null, 'Masukkan User ID Roblox Anda. [DATA DEMO]', true,
                [['80 Robux', 15000], ['400 Robux', 75000], ['800 Robux', 150000], ['1.700 Robux', 300000]]],
            ['game', 'Free Fire', 'free-fire', true, false, 'Player ID', null, 'Buka profil Free Fire, salin Player ID. [DATA DEMO]', true,
                [['70 Diamond', 10000], ['140 Diamond', 20000], ['355 Diamond', 50000], ['720 Diamond', 100000]]],
            ['game', 'Mobile Legends', 'mobile-legends', true, true, 'User ID', 'Zone ID', 'Isi User ID dan Zone ID (tampil di profil). [DATA DEMO]', true,
                [['86 Diamond', 22000], ['172 Diamond', 44000], ['257 Diamond', 66000], ['706 Diamond', 180000]]],
            ['pulsa', 'Pulsa Telkomsel', 'pulsa-telkomsel', true, false, 'Nomor HP', null, 'Contoh: 08123456789. [DATA DEMO]', false,
                [['Pulsa 10.000', 11500], ['Pulsa 25.000', 26500], ['Pulsa 50.000', 51500]]],
            ['ewallet', 'Saldo DANA', 'saldo-dana', true, false, 'Nomor DANA', null, 'Nomor HP yang terdaftar di DANA. [DATA DEMO]', false,
                [['DANA 20.000', 21500], ['DANA 50.000', 51500], ['DANA 100.000', 101500]]],
            ['token-listrik', 'Token PLN', 'token-pln', true, false, 'ID Pelanggan / No. Meter', null, 'Nomor meter / ID pelanggan PLN. [DATA DEMO]', false,
                [['Token 20.000', 22000], ['Token 50.000', 52000], ['Token 100.000', 102000]]],
        ];
        foreach ($products as $i => [$cat, $name, $slug, $needUid, $needSrv, $uidLabel, $srvLabel, $hint, $popular, $variants]) {
            $p = Product::create([
                'category_id' => $cats[$cat]->id, 'name' => $name, 'slug' => $slug, 'description' => 'Produk simulasi untuk pengembangan V-TAK.',
                'needs_user_id' => $needUid, 'needs_server_id' => $needSrv, 'user_id_label' => $uidLabel,
                'server_id_label' => $srvLabel, 'user_id_hint' => $hint, 'is_popular' => $popular, 'sort_order' => $i,
            ]);
            foreach ($variants as $j => [$vn, $price]) {
                $p->variants()->create(['name' => $vn, 'price' => $price, 'sort_order' => $j]);
            }
        }

        foreach ([
            ['qris', 'QRIS (Simulasi)', 'qris', 0, 0.70], ['va_bca', 'Virtual Account BCA (Simulasi)', 'va', 4000, 0],
            ['dana', 'DANA (Simulasi)', 'ewallet', 0, 1.50],
        ] as $i => [$code, $name, $type, $flat, $pct]) {
            PaymentMethod::create(['code' => $code, 'name' => $name, 'type' => $type, 'fee_flat' => $flat, 'fee_percent' => $pct, 'sort_order' => $i]);
        }

        Promotion::create(['code' => 'DEMO10', 'description' => 'Diskon 10% maks Rp10.000 (demo)', 'type' => 'percent', 'value' => 10, 'max_discount' => 10000, 'min_purchase' => 10000, 'quota' => 100]);

        Banner::create(['title' => 'Top Up Cepat & Transparan', 'subtitle' => 'Harga jelas sebelum bayar. Pantau status pesanan kapan saja.', 'link_url' => '/games.html', 'sort_order' => 1]);
        Banner::create(['title' => 'Pakai kode DEMO10', 'subtitle' => 'Diskon 10% (maks Rp10.000) — data demo untuk uji promo.', 'link_url' => '/games.html?group=game', 'sort_order' => 2]);
    }
}
