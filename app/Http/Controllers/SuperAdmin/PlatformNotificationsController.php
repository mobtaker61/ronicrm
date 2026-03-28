<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\PlatformNotificationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlatformNotificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(): Response
    {
        $settings = PlatformNotificationSettings::get();

        return Inertia::render('SuperAdmin/PlatformNotifications/Index', [
            'settings' => [
                'owner_emails' => $settings['owner_emails'],
                'email_user_welcome' => (bool) $settings['email_user_welcome'],
                'email_owner_new_registration' => (bool) $settings['email_owner_new_registration'],
                'email_org_subscription_reminder' => (bool) $settings['email_org_subscription_reminder'],
                'subscription_reminder_days' => $settings['subscription_reminder_days'],
            ],
            'envOwnerEmailsHint' => env('MAIL_PLATFORM_OWNER_EMAILS', ''),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'owner_emails' => 'nullable|array',
            'owner_emails.*' => 'nullable|email',
            'email_user_welcome' => 'boolean',
            'email_owner_new_registration' => 'boolean',
            'email_org_subscription_reminder' => 'boolean',
            'subscription_reminder_days' => 'required|array|min:1',
            'subscription_reminder_days.*' => 'integer|min:0|max:365',
        ]);

        $normalized = PlatformNotificationSettings::get();
        $normalized['owner_emails'] = array_values(array_filter($validated['owner_emails'] ?? [], fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)));
        $normalized['email_user_welcome'] = (bool) ($validated['email_user_welcome'] ?? false);
        $normalized['email_owner_new_registration'] = (bool) ($validated['email_owner_new_registration'] ?? false);
        $normalized['email_org_subscription_reminder'] = (bool) ($validated['email_org_subscription_reminder'] ?? false);
        $normalized['subscription_reminder_days'] = array_values(array_unique(array_map('intval', $validated['subscription_reminder_days'])));

        Setting::set(PlatformNotificationSettings::SETTING_KEY, $normalized);

        return redirect()->back()->with('success', __('flash.superadmin_notifications_saved'));
    }
}
