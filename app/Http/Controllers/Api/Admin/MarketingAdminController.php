<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\Banner;
use App\Models\Promotion;
use Illuminate\Http\Request;

class MarketingAdminController extends ApiController
{
    // ---------- Promo ----------
    public function promos()
    {
        return $this->ok(Promotion::orderByDesc('id')->get());
    }

    public function promoStore(Request $r)
    {
        $p = Promotion::create($this->promoRules($r));
        AdminActivityLog::record($r->user(), 'promo.create', 'promo:'.$p->id);

        return $this->ok($p, 'Promo dibuat.', 201);
    }

    public function promoUpdate(Request $r, Promotion $promotion)
    {
        $promotion->update($this->promoRules($r, $promotion->id));
        AdminActivityLog::record($r->user(), 'promo.update', 'promo:'.$promotion->id);

        return $this->ok($promotion, 'Promo diperbarui.');
    }

    public function promoDestroy(Request $r, Promotion $promotion)
    {
        if ($promotion->used_count > 0) {
            $promotion->update(['is_active' => false]);

            return $this->ok(null, 'Promo sudah pernah dipakai, jadi dinonaktifkan.');
        }
        $promotion->delete();
        AdminActivityLog::record($r->user(), 'promo.delete', 'promo:'.$promotion->id);

        return $this->ok(null, 'Promo dihapus.');
    }

    private function promoRules(Request $r, ?int $ignore = null): array
    {
        $d = $r->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:30', 'unique:promotions,code'.($ignore ? ','.$ignore : '')],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);
        if ($d['type'] === 'percent' && $d['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'Persentase maksimal 100.']);
        }
        $d['code'] = strtoupper($d['code']);
        $d['min_purchase'] = $d['min_purchase'] ?? 0;

        return $d;
    }

    // ---------- Banner ----------
    public function banners()
    {
        return $this->ok(Banner::orderBy('sort_order')->get());
    }

    public function bannerStore(Request $r)
    {
        $b = Banner::create($this->bannerRules($r));
        AdminActivityLog::record($r->user(), 'banner.create', 'banner:'.$b->id);

        return $this->ok($b, 'Banner dibuat.', 201);
    }

    public function bannerUpdate(Request $r, Banner $banner)
    {
        $banner->update($this->bannerRules($r));
        AdminActivityLog::record($r->user(), 'banner.update', 'banner:'.$banner->id);

        return $this->ok($banner, 'Banner diperbarui.');
    }

    public function bannerDestroy(Request $r, Banner $banner)
    {
        $banner->delete();
        AdminActivityLog::record($r->user(), 'banner.delete', 'banner:'.$banner->id);

        return $this->ok(null, 'Banner dihapus.');
    }

    private function bannerRules(Request $r): array
    {
        $d = $r->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/[A-Za-z0-9_\-\.\/\?=&#]*|https?:\/\/.+)$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }
}
