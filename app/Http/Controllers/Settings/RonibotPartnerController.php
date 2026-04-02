<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\I18nService;
use App\Services\RoniBotPartnerApiService;
use App\Support\RonibotUrlDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RonibotPartnerController extends Controller
{
    public function __construct(
        protected I18nService $i18n
    ) {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canManageOrganizationSettings()) {
                abort(403, $this->i18n->translate('settings.ronibot_partner_error_unauthorized'));
            }

            return $next($request);
        });
    }

    public function register(Request $request, RoniBotPartnerApiService $partner): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:32'],
            /** همان رمز ورود به CRM؛ RoniBot نمی‌تواند هش bcrypt را بپذیرد، باید رمز ساده ارسال شود. */
            'password' => ['required', 'current_password'],
        ]);

        if (! $partner->isConfigured()) {
            return response()->json(['ok' => false, 'message' => $partner->configurationErrorMessage()], 503);
        }

        $user = Auth::user();
        $planRaw = config('services.ronibot.partner_default_plan_id');
        $planId = ($planRaw !== null && $planRaw !== '') ? (int) $planRaw : null;

        $phone = $this->normalizePartnerPhoneInput((string) $request->input('phone', ''));

        try {
            $data = $partner->register(
                (string) $user->name,
                (string) $user->email,
                (string) $request->password,
                $phone,
                $planId
            );
        } catch (\Throwable $e) {
            Log::warning('Ronibot partner register failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $authkey = $this->pick($data, ['authkey', 'auth_key']);
        if (! is_string($authkey) || $authkey === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_invalid_authkey')], 422);
        }

        $userId = $this->pick($data, ['user_id', 'userId']);
        $phoneNormalized = $this->pick($data, ['phone']);
        $phoneDisplay = is_string($phoneNormalized) && $phoneNormalized !== '' ? $phoneNormalized : $phone;
        $current = Setting::getForOrganization('ronibot', []);
        $merge = array_merge($current, [
            'authkey' => $authkey,
            'ronibot_user_id' => $userId !== null ? (string) $userId : ($current['ronibot_user_id'] ?? ''),
            'ronibot_phone' => $phoneDisplay,
            'line_phone' => $phoneDisplay,
        ]);
        Setting::setForOrganization('ronibot', $merge);

        return response()->json([
            'ok' => true,
            'message' => $this->i18n->translate('settings.ronibot_partner_success_registered'),
        ]);
    }

    public function createDevice(Request $request, RoniBotPartnerApiService $partner): JsonResponse
    {
        $request->validate([
            'device_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
        ]);

        if (! $partner->isConfigured()) {
            return response()->json(['ok' => false, 'message' => $partner->configurationErrorMessage()], 503);
        }

        $settings = Setting::getForOrganization('ronibot', []);
        $authkey = $settings['authkey'] ?? '';
        if ($authkey === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_register_first')], 422);
        }

        $org = Organization::query()->find(Auth::user()->current_organization_id);
        $name = $request->input('device_name') ?: ($org?->name ?: $this->i18n->translate('settings.ronibot_partner_default_device_name'));
        $webhook = $this->resolveWebhookUrlForPartner($settings);
        if ($webhook === '') {
            return response()->json([
                'ok' => false,
                'message' => $this->i18n->translate('settings.ronibot_partner_error_webhook_invalid'),
            ], 422);
        }
        if (trim((string) ($settings['webhook_url'] ?? '')) === '') {
            Setting::setForOrganization('ronibot', array_merge($settings, ['webhook_url' => $webhook]));
        }

        $phoneRaw = $request->input('phone');
        $phoneDevice = is_string($phoneRaw) && $phoneRaw !== ''
            ? $this->normalizePartnerPhoneInput($phoneRaw)
            : null;

        try {
            $data = $partner->createDevice(
                $authkey,
                $name,
                $webhook,
                $phoneDevice
            );
        } catch (\Throwable $e) {
            Log::warning('Ronibot partner createDevice failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $deviceId = $this->pick($data, ['device_id', 'deviceId']);
        $deviceUuid = $this->pick($data, ['device_uuid', 'deviceUuid']);
        if ($deviceUuid === null || $deviceUuid === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_invalid_device_uuid')], 422);
        }

        $current = Setting::getForOrganization('ronibot', []);
        Setting::setForOrganization('ronibot', array_merge($current, [
            'device_uuid' => (string) $deviceUuid,
            'device_id' => $deviceId !== null ? (string) $deviceId : '',
        ]));

        $existing = $this->pick($data, ['existing']);
        $existingBool = $existing === true || $existing === 1 || $existing === '1' || $existing === 'true';

        return response()->json([
            'ok' => true,
            'message' => $existingBool
                ? $this->i18n->translate('settings.ronibot_partner_success_device_reused')
                : $this->i18n->translate('settings.ronibot_partner_success_device_created'),
            'device_id' => $deviceId,
            'device_uuid' => (string) $deviceUuid,
            'existing' => $existingBool,
        ]);
    }

    public function qr(RoniBotPartnerApiService $partner): JsonResponse
    {
        if (! $partner->isConfigured()) {
            return response()->json(['ok' => false, 'message' => $partner->configurationErrorMessage()], 503);
        }

        $settings = Setting::getForOrganization('ronibot', []);
        $authkey = $settings['authkey'] ?? '';
        $deviceUuid = $settings['device_uuid'] ?? '';
        if ($authkey === '' || $deviceUuid === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_create_device_first')], 422);
        }

        try {
            $data = $partner->deviceQr($authkey, $deviceUuid);
        } catch (\Throwable $e) {
            Log::warning('Ronibot partner qr failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $qrcode = $this->pick($data, ['qrcode', 'qr', 'qr_code']);
        if (! is_string($qrcode) || $qrcode === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_qr_missing')], 422);
        }

        return response()->json([
            'ok' => true,
            'qrcode' => $qrcode,
        ]);
    }

    public function status(RoniBotPartnerApiService $partner): JsonResponse
    {
        if (! $partner->isConfigured()) {
            return response()->json(['ok' => false, 'message' => $partner->configurationErrorMessage()], 503);
        }

        $settings = Setting::getForOrganization('ronibot', []);
        $authkey = $settings['authkey'] ?? '';
        $deviceUuid = $settings['device_uuid'] ?? '';
        if ($authkey === '' || $deviceUuid === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_device_not_set')], 422);
        }

        try {
            $data = $partner->deviceStatus($authkey, $deviceUuid);
        } catch (\Throwable $e) {
            Log::warning('Ronibot partner status failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        $sessionStatusRaw = (string) ($payload['session_status'] ?? '');
        $sessionStatusLower = strtolower(trim($sessionStatusRaw));

        $linePhone = $this->pick($data, ['line_phone', 'linePhone', 'phone']);
        $sessionId = $this->pick($data, ['wa_session_id', 'session_id', 'session']);
        if (is_string($linePhone) && $linePhone !== '') {
            $current = Setting::getForOrganization('ronibot', []);
            Setting::setForOrganization('ronibot', array_merge($current, ['line_phone' => $linePhone]));
        }
        if (is_string($sessionId) && $sessionId !== '') {
            $current = Setting::getForOrganization('ronibot', []);
            Setting::setForOrganization('ronibot', array_merge($current, ['wa_session_id' => $sessionId]));
        }

        $did = $payload['device_id'] ?? $this->pick($data, ['device_id', 'deviceId']);
        $duuid = $payload['device_uuid'] ?? $this->pick($data, ['device_uuid', 'deviceUuid']);
        if ($did !== null && $did !== '') {
            $current = Setting::getForOrganization('ronibot', []);
            Setting::setForOrganization('ronibot', array_merge($current, [
                'device_id' => (string) $did,
                'device_uuid' => ($duuid !== null && $duuid !== '') ? (string) $duuid : ($current['device_uuid'] ?? ''),
            ]));
        }

        /** ریشه API ممکن است success: false بدهد بدون exception — هرگز (bool) روی رشته نزنید. */
        $apiSuccess = $data['success'] ?? $payload['success'] ?? null;
        if ($apiSuccess === false) {
            $connected = false;
        } else {
            $connected = $this->jsonTruthy($payload['connected'] ?? false);
        }

        $sessionOk = in_array($sessionStatusLower, ['authenticated', 'connected'], true);

        /**
         * هرگز فقط با session یا فقط با connected تأیید نکن — هر دو باید هم‌زمان درست باشند
         * تا createApp زود صدا نشود و خطای «Active device not found» نخورد.
         */
        $authenticated = $connected && $sessionOk;

        return response()->json([
            'ok' => true,
            'connected' => $authenticated,
            'session_status' => $sessionStatusRaw,
            'device_id' => $did,
            'device_uuid' => $duuid,
        ]);
    }

    public function createApp(Request $request, RoniBotPartnerApiService $partner): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:120',
        ]);

        if (! $partner->isConfigured()) {
            return response()->json(['ok' => false, 'message' => $partner->configurationErrorMessage()], 503);
        }

        $settings = Setting::getForOrganization('ronibot', []);
        $authkey = $settings['authkey'] ?? '';
        $deviceId = isset($settings['device_id']) ? (int) $settings['device_id'] : 0;
        $webhook = $this->resolveWebhookUrlForPartner($settings);
        if ($authkey === '' || $deviceId <= 0 || $webhook === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_prerequisites')], 422);
        }

        $org = Organization::query()->find(Auth::user()->current_organization_id);
        $brand = $this->i18n->translate('settings.ronibot_partner_app_title_brand');
        $title = $request->input('title') ?: ($org?->name
            ? sprintf($this->i18n->translate('settings.ronibot_partner_app_title_with_org'), $brand, (string) $org->name)
            : $brand);

        try {
            $data = $partner->createApp($authkey, $deviceId, $title, $webhook);
        } catch (\Throwable $e) {
            Log::warning('Ronibot partner createApp failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $appkey = $this->pick($data, ['app_key', 'appkey', 'appKey']);
        if (! is_string($appkey) || $appkey === '') {
            return response()->json(['ok' => false, 'message' => $this->i18n->translate('settings.ronibot_partner_error_invalid_app_key')], 422);
        }

        $defaultCreateMessage = RonibotUrlDefaults::createMessageUrl();
        if ($defaultCreateMessage === '') {
            $defaultCreateMessage = RonibotUrlDefaults::normalizeCreateMessageUrl((string) ($settings['api_url'] ?? ''));
        }

        $current = Setting::getForOrganization('ronibot', []);
        Setting::setForOrganization('ronibot', array_merge($current, [
            'appkey' => $appkey,
            'api_url' => $defaultCreateMessage !== '' ? $defaultCreateMessage : ($current['api_url'] ?? ''),
            'enabled' => true,
        ]));

        return response()->json([
            'ok' => true,
            'message' => $this->i18n->translate('settings.ronibot_partner_success_app_saved'),
        ]);
    }

    /**
     * همان منطق پیش‌نمایش فرم: مقدار ذخیره‌شده، یا env، یا APP_URL/wpwebhook.
     *
     * @param  array<string, mixed>  $settings
     */
    private function resolveWebhookUrlForPartner(array $settings): string
    {
        $w = trim((string) ($settings['webhook_url'] ?? ''));
        if ($w !== '') {
            return $w;
        }

        return RonibotUrlDefaults::webhookUrl();
    }

    /** @param  mixed  $value  مقدار JSON (bool، 0/1، یا رشته) */
    private function jsonTruthy(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if ($value === false || $value === 0 || $value === null) {
            return false;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));

            return in_array($lower, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * نرمال‌سازی شماره مثل RoniBot: trim، حذف NBSP و فاصله/خط تیره.
     * بدون preg (برخی بیلدهای PHP/PCRE2 روی ویندوز با الگوهای حاوی \s یا /u خطا می‌دهند).
     */
    private function normalizePartnerPhoneInput(string $phone): string
    {
        $s = trim($phone);
        $s = str_replace("\xC2\xA0", '', $s);

        $strip = [' ', "\t", "\n", "\r", "\f", "\v", '-', '‐', '‑', '‒', '–', '—', '−'];

        return str_replace($strip, '', $s);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pick(array $data, array $keys): mixed
    {
        $nested = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        $layers = $nested !== [] ? [$nested, $data] : [$data];

        foreach ($layers as $layer) {
            foreach ($keys as $k) {
                if (! array_key_exists($k, $layer)) {
                    continue;
                }
                $v = $layer[$k];
                if ($v === null) {
                    continue;
                }
                if ($v === '') {
                    continue;
                }

                return $v;
            }
        }

        return null;
    }
}
