<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayContract;
use App\Services\Payments\SimulationGateway;
use App\Services\Providers\ProviderContract;
use App\Services\Providers\SimulationProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderContract::class, function () {
            return match (config('vtak.provider')) {
                'simulation' => new SimulationProvider(),
                default => throw new \RuntimeException(
                    'Provider "'.config('vtak.provider').'" belum diimplementasikan. Buat class yang meng-implement ProviderContract sesuai dokumentasi resmi provider.'
                ),
            };
        });

        $this->app->bind(PaymentGatewayContract::class, fn () => new SimulationGateway());
    }

    public function boot(): void
    {
        date_default_timezone_set('Asia/Jakarta');
        config(['app.timezone' => 'Asia/Jakarta']);

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('orders', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('track', fn (Request $r) => Limit::perMinute(15)->by($r->ip()));
    }
}
