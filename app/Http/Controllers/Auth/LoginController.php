<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;   // <-- Tambahkan ini
use Illuminate\Support\Facades\Hash; // <-- Tambahkan ini
use App\Models\User;                 // <-- Tambahkan ini

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('login.index');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1. Cari user berdasarkan username
        $user = User::where('username', $request->username)->first();

        // 2. Cek apakah user ada dan password cocok
        if ($user && Hash::check($request->password, $user->password)) {

            // 3. CEK APAKAH SUDAH ADA SESI AKTIF DI DATABASE?
            // Kita cari di tabel sessions apakah ada user_id ini
            // Dan pastikan sesinya belum expired (last_activity dalam batas session lifetime)
            $sessionLifeTime = config('session.lifetime'); // Biasanya 120 menit
            $lastActivityLimit = now()->subMinutes($sessionLifeTime)->getTimestamp();

            $activeSession = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('last_activity', '>', $lastActivityLimit)
                ->exists();

            if ($activeSession) {
                // JIKA ADA SESI LAIN: TOLAK LOGIN
                return back()
                    ->with('error', 'Akun sedang digunakan di perangkat lain. Harap logout dari perangkat sebelumnya.')
                    ->withInput();
            }

            // 4. JIKA TIDAK ADA SESI LAIN: IZINKAN LOGIN
            Auth::login($user, $request->filled('remember'));
            $request->session()->regenerate();

            return redirect()->intended('/dashboard')->with('success', 'Login berhasil!');
        }

        // Jika username/password salah
        return back()->with('error', 'Username atau password salah')->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}