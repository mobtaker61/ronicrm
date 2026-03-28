<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Notifications\WelcomeRegisteredNotification;
use App\Support\PlatformNotificationSettings;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('flash.verification_resent'));
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        $user = $request->user()->fresh();
        $organization = Organization::query()->find($user->current_organization_id);

        if ($organization && PlatformNotificationSettings::get()['email_user_welcome']) {
            try {
                $user->notify(new WelcomeRegisteredNotification($organization));
            } catch (\Throwable $e) {
                Log::warning('Welcome email after verification failed: '.$e->getMessage());
            }
        }

        return redirect()->route('dashboard')->with('success', __('flash.email_verified_welcome'));
    }
}
