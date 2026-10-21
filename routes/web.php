<?php

use App\Http\Controllers\Api\Admin\CatalogAdminController as CatA;
use App\Http\Controllers\Api\Admin\CustomerAdminController;
use App\Http\Controllers\Api\Admin\MarketingAdminController as MktA;
use App\Http\Controllers\Api\Admin\OrderAdminController;
use App\Http\Controllers\Api\Admin\ReportAdminController;
use App\Http\Controllers\Api\Admin\SettingAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Beranda statis (tanpa Blade). Halaman HTML lain dilayani langsung oleh web server dari folder public/.
Route::get('/', fn () => response()->file(public_path('index.html')));

/*
| API V-TAK. Ditempatkan di routes/web.php dengan prefix "api" agar memakai session cookie (HttpOnly)
| + CSRF bawaan Laravel — tanpa menyimpan token di localStorage.
*/
Route::prefix('api')->group(function () {
    // Publik
    Route::get('csrf', [AuthController::class, 'csrf']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('site', [CatalogController::class, 'site']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{slug}', [CatalogController::class, 'show']);
    Route::get('payment-methods', [CatalogController::class, 'paymentMethods']);
    Route::get('banners', [CatalogController::class, 'banners']);
    Route::get('developers', [\App\Http\Controllers\Api\DeveloperController::class, 'index']);
    Route::post('quote', [CatalogController::class, 'quote'])->middleware('throttle:orders');

    // Auth
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'logout']);

    // Pesanan
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::get('orders/track', [OrderController::class, 'track'])->middleware('throttle:track');
    Route::post('orders/{orderNo}/simulate-pay', [OrderController::class, 'simulatePay'])->middleware('throttle:track');

    // Webhook (verifikasi signature, bebas CSRF)
    Route::post('webhooks/payment', [WebhookController::class, 'payment']);

    // Pelanggan login
    Route::middleware('auth')->group(function () {
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::get('my/orders', [OrderController::class, 'mine']);
    });

    // Admin
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('dashboard', [ReportAdminController::class, 'dashboard']);
        Route::get('reports', [ReportAdminController::class, 'report']);
        Route::get('reports/export', [ReportAdminController::class, 'export']);

        Route::get('categories', [CatA::class, 'categories']);
        Route::post('categories', [CatA::class, 'categoryStore']);
        Route::put('categories/{category}', [CatA::class, 'categoryUpdate']);
        Route::delete('categories/{category}', [CatA::class, 'categoryDestroy']);

        Route::get('products', [CatA::class, 'products']);
        Route::post('products', [CatA::class, 'productStore']);
        Route::get('products/{product}', [CatA::class, 'productShow']);
        Route::put('products/{product}', [CatA::class, 'productUpdate']);
        Route::delete('products/{product}', [CatA::class, 'productDestroy']);
        Route::post('products/{product}/image', [CatA::class, 'productImage']);
        Route::get('products/{product}/variants', [CatA::class, 'variants']);
        Route::post('products/{product}/variants', [CatA::class, 'variantStore']);
        Route::put('variants/{variant}', [CatA::class, 'variantUpdate']);
        Route::delete('variants/{variant}', [CatA::class, 'variantDestroy']);

        Route::get('orders', [OrderAdminController::class, 'index']);
        Route::get('orders/{order}', [OrderAdminController::class, 'show']);
        Route::put('orders/{order}', [OrderAdminController::class, 'update']);
        Route::post('orders/{order}/retry', [OrderAdminController::class, 'retry']);

        Route::get('customers', [CustomerAdminController::class, 'index']);
        Route::get('customers/{user}', [CustomerAdminController::class, 'show']);
        Route::put('customers/{user}', [CustomerAdminController::class, 'update']);

        Route::get('promos', [MktA::class, 'promos']);
        Route::post('promos', [MktA::class, 'promoStore']);
        Route::put('promos/{promotion}', [MktA::class, 'promoUpdate']);
        Route::delete('promos/{promotion}', [MktA::class, 'promoDestroy']);
        Route::get('banners', [MktA::class, 'banners']);
        Route::post('banners', [MktA::class, 'bannerStore']);
        Route::put('banners/{banner}', [MktA::class, 'bannerUpdate']);
        Route::delete('banners/{banner}', [MktA::class, 'bannerDestroy']);

        Route::get('settings', [SettingAdminController::class, 'settings']);
        Route::put('settings', [SettingAdminController::class, 'settingsUpdate']);
        Route::get('payment-methods', [SettingAdminController::class, 'methods']);
        Route::post('payment-methods', [SettingAdminController::class, 'methodStore']);
        Route::put('payment-methods/{method}', [SettingAdminController::class, 'methodUpdate']);
        Route::delete('payment-methods/{method}', [SettingAdminController::class, 'methodDestroy']);
        Route::get('logs', [SettingAdminController::class, 'logs']);

        $dev = \App\Http\Controllers\Api\Admin\DeveloperAdminController::class;
        Route::get('developers', [$dev, 'index']);
        Route::post('developers', [$dev, 'store']);
        Route::put('developers-project', [$dev, 'project']);
        Route::put('developers/{developer}', [$dev, 'update']);
        Route::delete('developers/{developer}', [$dev, 'destroy']);
        Route::post('developers/{developer}/image', [$dev, 'image']);
        Route::delete('developers/{developer}/image', [$dev, 'imageDestroy']);
    });

    // Endpoint API yang tidak ada -> JSON 404 (bukan halaman HTML)
    Route::any('{any}', fn () => response()->json(['status' => 'error', 'message' => 'Endpoint tidak ditemukan.', 'data' => null], 404))->where('any', '.*');
});
