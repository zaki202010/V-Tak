<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function csrf()
    {
        return $this->ok(['token' => csrf_token()]);
    }

    public function me(Request $r)
    {
        return $this->ok($r->user()?->toPublic());
    }

    public function register(Request $r)
    {
        if ($r->attributes->get('area') === 'admin') {
            return $this->fail('Registrasi tidak tersedia di area admin.', 403);
        }
        $d = $r->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^[0-9+]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['email.unique' => 'Email sudah terdaftar.', 'password.confirmed' => 'Konfirmasi kata sandi tidak sama.']);

        $user = User::create($d);
        Auth::login($user);
        $r->session()->regenerate();

        return $this->ok($user->toPublic(), 'Registrasi berhasil.', 201);
    }

    public function login(Request $r)
    {
        $c = $r->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $user = User::where('email', $c['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($c['password'], $user->password)) {
            return $this->fail('Email atau kata sandi salah, atau akun nonaktif.', 401);
        }

        $adminArea = $r->attributes->get('area') === 'admin';
        if ($adminArea && $user->role !== 'admin') {
            return $this->fail('Akun ini bukan akun admin.', 403);
        }
        if (! $adminArea && $user->role === 'admin') {
            // Kata sandi benar. Beri tanda agar halaman login mengulang login di area admin (sesi terpisah).
            return $this->fail('Akun admin.', 403, ['area' => ['admin']]);
        }

        Auth::login($user);
        $r->session()->regenerate();

        return $this->ok($user->toPublic(), 'Login berhasil.');
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return $this->ok(null, 'Anda telah keluar.');
    }

    public function updateProfile(Request $r)
    {
        $d = $r->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'regex:/^[0-9+]{8,20}$/'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $u = $r->user();
        if (! empty($d['password'])) {
            if (! Hash::check($d['current_password'] ?? '', $u->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
            }
            $u->password = $d['password'];
        }
        $u->name = $d['name'];
        $u->phone = $d['phone'] ?? null;
        $u->save();

        return $this->ok($u->toPublic(), 'Profil diperbarui.');
    }
}
