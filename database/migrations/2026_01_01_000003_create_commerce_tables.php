<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('type', 20)->default('manual'); // qris | va | ewallet | manual
            $t->unsignedInteger('fee_flat')->default(0);
            $t->decimal('fee_percent', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('description')->nullable();
            $t->string('type', 10)->default('percent'); // percent | fixed
            $t->unsignedInteger('value');
            $t->unsignedInteger('max_discount')->nullable();
            $t->unsignedInteger('min_purchase')->default(0);
            $t->unsignedInteger('quota')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no', 30)->unique();
            $t->string('tracking_token_hash', 64);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('product_id')->constrained('products');
            $t->foreignId('product_variant_id')->constrained('product_variants');
            $t->string('product_name');
            $t->string('variant_name');
            $t->string('target_user_id', 50)->nullable();
            $t->string('target_server_id', 20)->nullable();
            $t->unsignedInteger('unit_price');
            $t->unsignedInteger('service_fee')->default(0);
            $t->unsignedInteger('discount')->default(0);
            $t->unsignedInteger('total');
            $t->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $t->string('payment_method_name');
            $t->string('promo_code', 30)->nullable();
            $t->string('payment_status', 20)->default('pending'); // pending|paid|expired|failed|refunded
            $t->string('topup_status', 20)->default('waiting');   // waiting|processing|success|failed|manual_review
            $t->string('provider_ref', 80)->nullable();
            $t->string('idempotency_key', 64)->nullable()->unique();
            $t->text('admin_note')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->index(['payment_status', 'topup_status']);
            $t->index('created_at');
            $t->index('user_id');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('method', 60);
            $t->unsignedInteger('amount');
            $t->string('status', 20)->default('pending');
            $t->string('gateway_ref', 80)->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('promotion_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $t->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('transaction_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('event', 40);
            $t->string('message')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('payment_methods');
    }
};
