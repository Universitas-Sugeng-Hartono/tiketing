<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Akses cepat hanya untuk local/debug — jangan tampil di production
        $demoUsers = collect();
        if ($this->allowsQuickSwitch()) {
            $demoUsers = User::with('role.parent')
                ->where(function ($q) {
                    $q->whereNotNull('role_id')
                      ->orWhere('user_type', 'admin');
                })
                ->latest('id')
                ->take(12)
                ->get();
        }

        return view('auth.login', compact('demoUsers'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Selamat datang di SuperTicket Kampus, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'email' => 'Email atau password tidak sesuai.',
        ])->onlyInput('email');
    }

    /**
     * Fitur Quick Switch Akun/Role (One-Click Role Switcher untuk demo & pengujian hirarki)
     */
    public function quickSwitch(User $user, Request $request)
    {
        if (!$this->allowsQuickSwitch()) {
            abort(403, 'Akses cepat dinonaktifkan di production.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Berhasil beralih ke akun {$user->name} (Role: {$user->role?->name})");
    }

    private function allowsQuickSwitch(): bool
    {
        return app()->environment('local') || (bool) config('app.debug');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
