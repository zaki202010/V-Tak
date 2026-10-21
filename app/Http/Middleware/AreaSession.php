<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Memisahkan sesi area admin dan area pelanggan lewat nama cookie sesi yang berbeda. */
class AreaSession
{
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->is('api/admin/*') || $request->header('X-VTAK-Area') === 'admin';
        config(['session.cookie' => $admin ? 'vtak_admin_session' : 'vtak_session']);
        $request->attributes->set('area', $admin ? 'admin' : 'customer');

        return $next($request);
    }
}
