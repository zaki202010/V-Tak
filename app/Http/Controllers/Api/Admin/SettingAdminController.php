<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingAdminController extends ApiController
{
    private const KEYS = ['site_name', 'tagline', 'contact_wa', 'contact_email', 'contact_hours', 'order_expiry_minutes'];

    public function settings()
    {
        return $this->ok(Setting::allKeyed() + ['mode' => config('vtak.mode')]);
    }

    public function settingsUpdate(Request $r)
    {
        $d = $r->validate([
            'site_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'contact_wa' => ['nullable', 'regex:/^[0-9]{8,16}$/'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_hours' => ['nullable', 'string', 'max:120'],
            'order_expiry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ]);
        foreach (self::KEYS as $k) {
            Setting::put($k, $d[$k] ?? '');
        }
        AdminActivityLog::record($r->user(), 'settings.update');

        return $this->ok(null, 'Pengaturan disimpan.');
    }

    public function methods()
    {
        return $this->ok(PaymentMethod::orderBy('sort_order')->get());
    }

    public function methodStore(Request $r)
    {
        $m = PaymentMethod::create($this->methodRules($r));
        AdminActivityLog::record($r->user(), 'method.create', 'method:'.$m->id);

        return $this->ok($m, 'Metode dibuat.', 201);
    }

    public function methodUpdate(Request $r, PaymentMethod $method)
    {
        $method->update($this->methodRules($r, $method->id));
        AdminActivityLog::record($r->user(), 'method.update', 'method:'.$method->id);

        return $this->ok($method, 'Metode diperbarui.');
    }

    public function methodDestroy(Request $r, PaymentMethod $method)
    {
        $method->delete();
        AdminActivityLog::record($r->user(), 'method.delete', 'method:'.$method->id);

        return $this->ok(null, 'Metode dihapus.');
    }

    private function methodRules(Request $r, ?int $ignore = null): array
    {
        $d = $r->validate([
            'code' => ['required', 'alpha_dash', 'max:30', 'unique:payment_methods,code'.($ignore ? ','.$ignore : '')],
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:qris,va,ewallet,manual'],
            'fee_flat' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $d['fee_flat'] = $d['fee_flat'] ?? 0;
        $d['fee_percent'] = $d['fee_percent'] ?? 0;
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }

    public function logs()
    {
        $p = AdminActivityLog::with('user:id,name')->orderByDesc('id')->paginate(25);

        return $this->paginated($p, fn ($l) => [
            'id' => $l->id, 'admin' => $l->user?->name, 'action' => $l->action, 'target' => $l->target,
            'ip' => $l->ip, 'created_at' => $l->created_at,
        ]);
    }
}
