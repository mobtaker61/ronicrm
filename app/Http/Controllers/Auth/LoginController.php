<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm(Request $request): mixed
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        $tab = $request->query('tab');
        $initialTab = $tab === 'register' ? 'register' : 'login';

        return Inertia::render('Auth/Login', [
            'initialTab' => $initialTab,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // Determine if input is email or username
        $field = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $request->email,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user && ! $user->hasVerifiedEmail()) {
                return redirect()->intended(route('verification.notice'));
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
