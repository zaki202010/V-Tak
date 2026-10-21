<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login terlebih dahulu.', 'data' => null], 401);
        }
        if ($user->role !== 'admin' || ! $user->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak berwenang mengakses fitur ini.', 'data' => null], 403);
        }

        return $next($request);
    }
}
