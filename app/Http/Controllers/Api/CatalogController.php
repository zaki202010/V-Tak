<?php

namespace App\Http\Controllers\Api;

use App\Models\Banner;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\PricingService;
use Illuminate\Http\Request;

class CatalogController extends ApiController
{
    public function categories()
    {
        return $this->ok(Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']));
    }

    public function products(Request $r)
    {
        $r->validate(['q' => ['nullable', 'string', 'max:60'], 'sort' => ['nullable', 'in:name,price_asc,price_desc']]);

        $q = Product::with('category:id,name,slug')
            ->withMin(['variants as min_price' => fn ($v) => $v->where('is_active', true)], 'price')
            ->where('is_active', true)
            ->whereHas('category', fn ($c) => $c->where('is_active', true));

        if ($s = trim((string) $r->query('q'))) {
            $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%');
        }
        if ($cat = $r->query('category')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $cat));
        }
        if ($g = $r->query('group')) {
            $q->whereHas('category', fn ($c) => $g === 'game' ? $c->where('slug', 'game') : $c->where('slug', '!=', 'game'));
        }
        if ($r->boolean('popular')) {
            $q->where('is_popular', true);
        }

        match ($r->query('sort')) {
            'price_asc' => $q->orderBy('min_price'),
            'price_desc' => $q->orderByDesc('min_price'),
            default => $q->orderBy('sort_order')->orderBy('name'),
        };

        $per = min((int) $r->query('per_page', 12), 48) ?: 12;

        return $this->paginated($q->paginate($per), fn (Product $p) => $p->toCard());
    }

    public function show(string $slug)
    {
        $p = Product::with(['category:id,name,slug', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->ok([
            'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'description' => $p->description,
            'image_url' => $p->imageUrl(), 'category' => $p->category,
            'needs_user_id' => $p->needs_user_id, 'needs_server_id' => $p->needs_server_id,
            'user_id_label' => $p->user_id_label, 'server_id_label' => $p->server_id_label ?: 'Server ID',
            'user_id_hint' => $p->user_id_hint,
            'variants' => $p->variants->map(fn ($v) => [
                'id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'original_price' => $v->original_price,
            ])->values(),
        ]);
    }

    public function paymentMethods()
    {
        return $this->ok(PaymentMethod::where('is_active', true)->orderBy('sort_order')
            ->get(['code', 'name', 'type', 'fee_flat', 'fee_percent']));
    }

    public function quote(Request $r, PricingService $pricing)
    {
        $d = $r->validate([
            'variant_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'max:30'],
            'promo_code' => ['nullable', 'string', 'max:30'],
        ]);
        $variant = ProductVariant::where('is_active', true)->whereHas('product', fn ($p) => $p->where('is_active', true))->findOrFail($d['variant_id']);
        $method = PaymentMethod::where('is_active', true)->where('code', $d['payment_method'])->firstOrFail();
        $code = strtoupper(trim((string) ($d['promo_code'] ?? '')));
        $promo = $code !== '' ? Promotion::where('code', $code)->first() : null;

        return $this->ok($pricing->quote($variant, $method, $promo, $code !== ''));
    }

    public function banners()
    {
        return $this->ok(Banner::where('is_active', true)->orderBy('sort_order')->get(['id', 'title', 'subtitle', 'link_url']));
    }

    public function site()
    {
        $s = Setting::allKeyed();

        return $this->ok([
            'site_name' => $s['site_name'] ?? 'V-TAK',
            'tagline' => $s['tagline'] ?? '',
            'contact_wa' => $s['contact_wa'] ?? '',
            'contact_email' => $s['contact_email'] ?? '',
            'contact_hours' => $s['contact_hours'] ?? '',
            'mode' => config('vtak.mode'),
        ]);
    }
}
