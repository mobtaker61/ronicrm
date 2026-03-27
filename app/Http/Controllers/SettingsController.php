<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SocialMediaType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canAccessSettings()) {
                abort(403, 'Unauthorized action. You do not have access to settings.');
            }

            return $next($request);
        });
    }

    public function index(): Response
    {
        $canManageOrganizationSettings = Auth::user()->canManageOrganizationSettings();
        $canManageSystemSettings = Auth::user()->isSuperAdmin();
        $isSuperAdmin = Auth::user()->isSuperAdmin();

        $users = [];
        $roles = [];
        $organizations = [];
        if ($canManageSystemSettings) {
            $users = \App\Models\User::with('roles')->orderBy('name')->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'created_at' => $user->created_at,
                ];
            });
            $roles = \Spatie\Permission\Models\Role::orderBy('name')->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                ];
            });
            if ($isSuperAdmin) {
                $organizations = \App\Models\Organization::query()
                    ->with(['users' => fn ($q) => $q->orderBy('name')])
                    ->withCount('users')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($organization) => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'is_active' => (bool) $organization->is_active,
                        'users_count' => $organization->users_count,
                        'members' => $organization->users->map(fn ($member) => [
                            'id' => $member->id,
                            'name' => $member->name,
                            'email' => $member->email,
                            'avatar_url' => $member->avatar_path ? Storage::url($member->avatar_path) : null,
                            'role_in_org' => $member->pivot?->role_in_org,
                            'status' => $member->pivot?->status,
                            'is_default' => (bool) ($member->pivot?->is_default ?? false),
                        ])->values(),
                    ]);
            }
        }

        $allowedTabs = $canManageSystemSettings
            ? ['smtp', 'ronibot', 'telegram', 'instagram', 'google-contacts', 'subscription', 'users']
            : ['smtp', 'ronibot', 'telegram', 'instagram', 'google-contacts', 'subscription'];
        $initialTab = in_array(request()->query('tab'), $allowedTabs, true)
            ? request()->query('tab')
            : 'smtp';

        return Inertia::render('Settings/Index', [
            'initialTab' => $initialTab,
            'isAdmin' => $canManageSystemSettings,
            'canManageOrganizationSettings' => $canManageOrganizationSettings,
            'canManageSystemSettings' => $canManageSystemSettings,
            'users' => $users,
            'roles' => $roles,
            'organizations' => $organizations,
            'socialMediaTypes' => SocialMediaType::orderBy('sort_order')->get(),
            'smtpSettings' => Setting::getForOrganization('smtp', [
                'host' => '',
                'port' => '587',
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from_address' => '',
                'from_name' => '',
                'enabled' => false,
                'save_to_sent' => false,
                'imap_host' => '',
                'imap_port' => '993',
                'imap_encryption' => 'ssl',
            ]),
            'ronibotSettings' => Setting::getForOrganization('ronibot', [
                'api_url' => 'https://ronibot.com/api/create-message',
                'appkey' => '',
                'authkey' => '',
                'webhook_url' => 'https://ronicrm.com/wpwebhook',
                'enabled' => false,
            ]),
            'telegramSettings' => array_merge(Setting::getScoped('telegram', [
                'bot_token' => '',
                'webhook_url' => '',
                'enabled' => false,
            ]), [
                'webhook_url_computed' => (function () {
                    $base = trim(env('TELEGRAM_WEBHOOK_URL', '') ?: config('app.url', ''));

                    return $base ? (rtrim($base, '/').'/telegram-webhook') : '';
                })(),
            ]),
            'instagramSettings' => Setting::getScoped('instagram', [
                'enabled' => false,
                'access_token' => '',
                'webhook_verify_token' => '',
            ]),
            'instagramConnection' => $this->getInstagramConnectionForFront(),
            'googleContactsIntegration' => $this->getGoogleContactsIntegrationForFront(),
            'googleContactsRedirectUri' => config('services.google_contacts.redirect_uri'),
            'telegramConnection' => $this->getTelegramConnectionForFront(),
            'instagramWebhookEvents' => $canManageSystemSettings ? $this->getInstagramWebhookEventsLast20() : [],
            'subscriptionSummary' => $this->getSubscriptionSummaryForFront(),
        ]);
    }

    protected function getSubscriptionSummaryForFront(): ?array
    {
        $orgId = \App\Support\OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return null;
        }
        $service = app(\App\Services\SubscriptionService::class);
        $sub = $service->getOrCreateForOrganization((int) $orgId);
        $sub->loadMissing('plan:id,name,code');

        return [
            'id' => $sub->id,
            'status' => $service->computeStatus($sub),
            'remaining_days' => $service->remainingDays($sub),
            'plan' => $sub->plan ? [
                'id' => $sub->plan->id,
                'name' => $sub->plan->name,
                'code' => $sub->plan->code,
            ] : null,
            'started_at' => $sub->started_at?->toIso8601String(),
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'ends_at' => $sub->ends_at?->toIso8601String(),
            'grace_ends_at' => $sub->grace_ends_at?->toIso8601String(),
        ];
    }

    protected function getTelegramConnectionForFront(): ?array
    {
        $conn = \App\Models\TelegramUserConnection::getActive();
        if (! $conn) {
            return null;
        }

        return [
            'id' => $conn->id,
            'phone' => $conn->phone ? substr($conn->phone, 0, 4).'***' : null,
            'telegram_username' => $conn->telegram_username,
            'status' => $conn->status,
            'last_used_at' => $conn->last_used_at?->toIso8601String(),
        ];
    }

    protected function getInstagramConnectionForFront(): ?array
    {
        $conn = \App\Models\InstagramConnection::getActive();
        if (! $conn) {
            return null;
        }

        return [
            'id' => $conn->id,
            'ig_business_account_id' => $conn->ig_business_account_id,
            'ig_username' => $conn->ig_username,
            'ig_profile_pic_url' => $conn->ig_profile_pic_url,
            'page_id' => $conn->page_id,
            'token_expires_at' => $conn->token_expires_at?->toIso8601String(),
            'token_valid' => ! $conn->isTokenExpired(),
            'scopes' => $conn->scopes_json,
            'webhook_verified_at' => $conn->webhook_verified_at?->toIso8601String(),
            'last_webhook_event_at' => $conn->last_webhook_event_at?->toIso8601String(),
        ];
    }

    protected function getGoogleContactsIntegrationForFront(): ?array
    {
        $row = \App\Models\GoogleContactsIntegration::getSingleton();
        if (! $row) {
            return null;
        }

        return [
            'account_email' => $row->account_email,
            'connected_at' => $row->created_at?->toIso8601String(),
        ];
    }

    protected function getInstagramWebhookEventsLast20(): array
    {
        return \App\Models\InstagramWebhookEvent::with('instagramConnection:id,ig_username')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'mid' => $e->mid,
                'sender_id' => $e->sender_id ? substr($e->sender_id, 0, 6).'***' : null,
                'recipient_id' => $e->recipient_id ? substr($e->recipient_id, 0, 6).'***' : null,
                'event_timestamp' => $e->event_timestamp?->toIso8601String(),
                'created_at' => $e->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function updateSmtp(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'enabled' => 'boolean',
            'save_to_sent' => 'boolean',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
        ]);

        // Don't update password if it's empty (keep existing)
        if (empty($validated['password'])) {
            $existing = Setting::getForOrganization('smtp', []);
            if (isset($existing['password'])) {
                $validated['password'] = $existing['password'];
            }
        }

        Setting::setForOrganization('smtp', $validated);

        // Update .env file or config cache if needed
        // For now, we'll just store in database

        return redirect()->back()
            ->with('success', 'SMTP settings updated successfully.');
    }

    public function updateRonibot(Request $request)
    {
        $validated = $request->validate([
            'api_url' => 'required|url|max:500',
            'appkey' => 'required|string|max:255',
            'authkey' => 'required|string|max:255',
            'webhook_url' => 'nullable|url|max:500',
            'enabled' => 'boolean',
        ]);

        Setting::setForOrganization('ronibot', $validated);

        return redirect()->back()
            ->with('success', 'Ronibot settings updated successfully.');
    }

    public function testRonibot(Request $request)
    {
        $validated = $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        try {
            $ronibotSettings = Setting::getForOrganization('ronibot', []);

            if (empty($ronibotSettings['appkey']) || empty($ronibotSettings['authkey'])) {
                return redirect()->back()
                    ->with('error', 'Please configure Ronibot settings first (App Key and Auth Key are required).');
            }

            if (! ($ronibotSettings['enabled'] ?? false)) {
                return redirect()->back()
                    ->with('error', 'Please enable Ronibot first.');
            }

            $whatsappService = app(\App\Services\WhatsAppService::class);
            $message = $validated['test_message'] ?? 'This is a test message from RoniCRM. If you received this message, your Ronibot settings are working correctly!';

            $result = $whatsappService->sendMessage($validated['test_phone'], $message);

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', 'Test WhatsApp message sent successfully to '.$validated['test_phone'].'!');
            } else {
                return redirect()->back()
                    ->with('error', 'Failed to send test message: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Ronibot test error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send test message: '.$e->getMessage());
        }
    }

    public function updateTelegram(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'nullable|string|max:500',
            'enabled' => 'boolean',
        ]);

        $previous = Setting::getScoped('telegram', []);
        // ادغام با قبلی تا در صورت عدم ارسال token از فرم، مقدار قبلی حفظ شود
        $toSave = array_merge($previous, $validated);
        Setting::setForOrganization('telegram', $toSave);

        // ثبت یا حذف وب‌هوک با تلگرام (الزامی برای دریافت پیام‌های ربات)
        $telegramService = app(\App\Services\TelegramService::class);
        $token = trim($toSave['bot_token'] ?? $validated['bot_token'] ?? '');
        $enabled = (bool) ($toSave['enabled'] ?? false);
        $prevToken = trim($previous['bot_token'] ?? '');

        if ($token !== '' && $enabled) {
            $webhookUrl = $this->getTelegramWebhookUrl();
            if ($webhookUrl !== '' && str_starts_with($webhookUrl, 'https://')) {
                Log::info('Telegram: setting webhook', ['url' => $webhookUrl]);
                $setResult = $telegramService->setWebhook($webhookUrl, $token);
                Log::info('Telegram: setWebhook result', $setResult);
                if (! $setResult['success']) {
                    return redirect()->back()
                        ->with('error', 'تنظیمات ذخیره شد، اما ثبت وب‌هوک تلگرام ناموفق بود: '.($setResult['error'] ?? 'خطای نامشخص'));
                }
            } else {
                Log::warning('Telegram: webhook URL invalid or not HTTPS', ['webhook_url' => $webhookUrl ?? '(empty)']);

                return redirect()->back()
                    ->with('error', 'برای وب‌هوک، APP_URL یا TELEGRAM_WEBHOOK_URL در .env باید با https:// باشد.');
            }
        } elseif ($prevToken !== '') {
            $telegramService->setWebhook('', $prevToken);
        }

        return redirect()->back()
            ->with('success', 'Telegram settings updated successfully.');
    }

    protected function getTelegramWebhookUrl(): string
    {
        $orgSlug = Auth::user()?->currentOrganization?->slug;
        $path = $orgSlug ? '/telegram-webhook/'.$orgSlug : '/telegram-webhook';

        $custom = trim(env('TELEGRAM_WEBHOOK_URL', ''));
        if ($custom !== '') {
            return str_contains($custom, '/telegram-webhook') ? $custom : rtrim($custom, '/').$path;
        }
        $base = rtrim(config('app.url', ''), '/');

        return $base ? ($base.$path) : '';
    }

    /**
     * ثبت دستی وب‌هوک تلگرام (برای تست و رفع مشکل در سرور).
     */
    public function registerTelegramWebhook(Request $request)
    {
        $settings = Setting::getScoped('telegram', []);
        $token = trim($settings['bot_token'] ?? '');
        if ($token === '') {
            return redirect()->route('settings.index', ['tab' => 'telegram'])
                ->with('error', 'ابتدا توکن ربات را ذخیره کنید.');
        }
        $webhookUrl = $this->getTelegramWebhookUrl();
        if ($webhookUrl === '' || ! str_starts_with($webhookUrl, 'https://')) {
            return redirect()->route('settings.index', ['tab' => 'telegram'])
                ->with('error', 'APP_URL یا TELEGRAM_WEBHOOK_URL در .env باید با https:// باشد.');
        }
        $result = app(\App\Services\TelegramService::class)->setWebhook($webhookUrl, $token);
        if ($result['success']) {
            return redirect()->route('settings.index', ['tab' => 'telegram'])
                ->with('success', 'وب‌هوک با موفقیت ثبت شد: '.$webhookUrl);
        }

        return redirect()->route('settings.index', ['tab' => 'telegram'])
            ->with('error', 'خطا در ثبت وب‌هوک: '.($result['error'] ?? 'نامشخص'));
    }

    public function testTelegram(Request $request)
    {
        try {
            // Allow testing with token from request (current form value) so user can test before saving
            $tokenFromRequest = $request->input('bot_token');
            $telegramService = app(\App\Services\TelegramService::class);
            $result = $tokenFromRequest
                ? $telegramService->getMeWithToken($tokenFromRequest)
                : $telegramService->getMe();

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', 'Telegram bot token is valid. Bot: @'.($result['username'] ?? 'unknown'));
            }

            return redirect()->back()
                ->with('error', 'Telegram test failed: '.($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Telegram test error: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'Telegram test failed: '.$e->getMessage());
        }
    }

    public function updateInstagram(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'access_token' => 'nullable|string|max:1000',
            'webhook_verify_token' => 'nullable|string|max:255',
        ]);

        $current = Setting::getScoped('instagram', []);
        if (empty($validated['access_token'])) {
            $validated['access_token'] = $current['access_token'] ?? '';
        }
        $validated['webhook_verify_token'] = $validated['webhook_verify_token'] ?? $current['webhook_verify_token'] ?? '';
        Setting::setForOrganization('instagram', $validated);

        return redirect()->back()
            ->with('success', 'Instagram settings updated successfully.');
    }

    public function revalidateInstagramToken(Request $request)
    {
        $conn = \App\Models\InstagramConnection::getActive();
        if (! $conn) {
            return redirect()->back()->with('error', 'No Instagram account connected.');
        }
        $service = app(\App\Services\MetaInstagramService::class);
        if ($service->refreshToken($conn)) {
            return redirect()->back()->with('success', 'Token revalidated successfully.');
        }

        return redirect()->back()->with('error', 'Token revalidation failed. Try disconnecting and connecting again.');
    }

    public function testSmtp(Request $request)
    {
        $validated = $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $smtpSettings = Setting::getForOrganization('smtp', []);

            if (empty($smtpSettings['host']) || empty($smtpSettings['username'])) {
                return redirect()->back()
                    ->with('error', 'Please configure SMTP settings first.');
            }

            if (empty($smtpSettings['password'])) {
                return redirect()->back()
                    ->with('error', 'SMTP password is required. Please save your SMTP settings with a password first.');
            }

            // Configure mail settings temporarily
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $smtpSettings['host'],
                'mail.mailers.smtp.port' => $smtpSettings['port'] ?? 587,
                'mail.mailers.smtp.encryption' => $smtpSettings['encryption'] ?? 'tls',
                'mail.mailers.smtp.username' => $smtpSettings['username'],
                'mail.mailers.smtp.password' => $smtpSettings['password'],
                'mail.from.address' => $smtpSettings['from_address'] ?? $smtpSettings['username'],
                'mail.from.name' => $smtpSettings['from_name'] ?? 'RoniCRM',
            ]);

            // Clear mailer instance to force reconfiguration
            app()->forgetInstance('mail.manager');

            Mail::raw('This is a test email from RoniCRM SMTP configuration. If you received this email, your SMTP settings are working correctly!', function ($message) use ($validated, $smtpSettings) {
                $message->to($validated['test_email'])
                    ->subject('RoniCRM SMTP Test Email')
                    ->from($smtpSettings['from_address'] ?? $smtpSettings['username'], $smtpSettings['from_name'] ?? 'RoniCRM');
            });

            return redirect()->back()
                ->with('success', 'Test email sent successfully to '.$validated['test_email'].'! Please check your inbox.');
        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            Log::error('SMTP Transport Error: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'SMTP connection failed: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('SMTP Test Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'smtp_settings' => [
                    'host' => $smtpSettings['host'] ?? null,
                    'port' => $smtpSettings['port'] ?? null,
                    'username' => $smtpSettings['username'] ?? null,
                ],
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }

    public function renewSubscription(Request $request)
    {
        $orgId = \App\Support\OrganizationContext::getOrganizationId();
        if (! $orgId || ! Auth::user()?->canManageOrganizationSettings((int) $orgId)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        $months = (int) ($validated['months'] ?? 1);
        $service = app(\App\Services\SubscriptionService::class);
        $sub = $service->getOrCreateForOrganization((int) $orgId);

        $base = $sub->ends_at && $sub->ends_at->isFuture() ? $sub->ends_at->copy() : now();
        $sub->started_at = $sub->started_at ?: now();
        $sub->ends_at = $base->addMonths($months);
        $sub->grace_ends_at = null;
        $sub->save();

        return redirect()->back()->with('success', 'اشتراک سازمان تمدید شد.');
    }
}
