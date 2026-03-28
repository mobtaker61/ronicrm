<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'تعداد درخواست‌ها زیاد است. چند دقیقه بعد دوباره تلاش کنید.']);
        }

        // پیام یکسان برای جلوگیری از افشای وجود یا عدم وجود ایمیل
        return back()->with(
            'success',
            'اگر این ایمیل در سیستم ثبت باشد، لینک بازیابی رمز به آن ارسال می‌شود.'
        );
    }
}
