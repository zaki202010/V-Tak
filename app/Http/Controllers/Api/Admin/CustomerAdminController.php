<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerAdminController extends ApiController
{
    public function index(Request $r)
    {
        $q = User::where('role', 'customer')->withCount('orders');
        if ($s = trim((string) $r->query('q'))) {
            $like = '%'.addcslashes($s, '%_\\').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        return $this->paginated($q->orderByDesc('id')->paginate(15), fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'is_active' => (bool) $u->is_active, 'orders_count' => $u->orders_count, 'created_at' => $u->created_at,
        ]);
    }

    public function show(User $user)
    {
        $orders = Order::where('user_id', $user->id)->orderByDesc('id')->limit(20)->get()->map->toPublic();

        return $this->ok(['user' => $user->toPublic() + ['is_active' => (bool) $user->is_active], 'orders' => $orders]);
    }

    public function update(Request $r, User $user)
    {
        $d = $r->validate(['is_active' => ['required', 'boolean']]);
        if ($user->role === 'admin') {
            return $this->fail('Akun admin tidak dapat diubah dari sini.', 403);
        }
        $user->forceFill(['is_active' => $d['is_active']])->save();
        AdminActivityLog::record($r->user(), 'customer.status', 'user:'.$user->id, $d);

        return $this->ok(null, 'Status akun diperbarui.');
    }
}
