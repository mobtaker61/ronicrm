<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\WhatsAppService;
use App\Services\WhatsAppSessionManager;
use App\Services\WhatsAppYarApiService;
use App\Support\WhatsAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WhatsAppConnectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canManageOrganizationSettings()) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        });
    }

    public function connect(Request $request, WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): JsonResponse
    {
        $org = Organization::query()->find(Auth::user()->current_organization_id);
        if (! $org) {
            return response()->json(['ok' => false, 'message' => 'Organization not found.'], 422);
        }

        if (! WhatsAppSettings::isConfigured($org->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'WhatsAppYar API key is not configured. Set WHATSAPPYAR_API_KEY in .env.',
            ], 503);
        }

        $api = $api->forOrganization($org->id);

        try {
            $resolved = $sessions->resolveOrCreateSession($api, $org);
            $sessionId = $resolved['session_id'];

            $api->ensureSessionStarted($sessionId);
            $webhookWarning = $this->tryEnsureWebhook($api, $org, $sessionId);

            return response()->json([
                'ok' => true,
                'session_id' => $sessionId,
                'session_recreated' => $resolved['recreated'],
                'message' => $resolved['recreated']
                    ? 'A new WhatsApp session was created. Scan the fresh QR code.'
                    : 'WhatsApp session started. Scan the QR code to connect.',
                'webhook_warning' => $webhookWarning,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp connect failed', ['error' => $e->getMessage(), 'org_id' => $org->id]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function qrCode(WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): JsonResponse
    {
        $orgId = (int) Auth::user()->current_organization_id;
        $org = Organization::query()->find($orgId);
        if (! $org) {
            return response()->json(['ok' => false, 'message' => 'Organization not found.'], 422);
        }

        $api = $api->forOrganization($orgId);

        try {
            $resolved = $sessions->resolveOrCreateSession($api, $org);
            $sessionId = $resolved['session_id'];
            $api->ensureSessionStarted($sessionId);
            $data = $api->getQrCode($sessionId);
            $qrCode = $data['qrCode'] ?? $data['data']['qrCode'] ?? null;
            $status = WhatsAppYarApiService::normalizeSessionStatus($data);

            if ($qrCode === null || $qrCode === '') {
                $session = $api->getSession($sessionId);
                $status = WhatsAppYarApiService::normalizeSessionStatus($session);
                if (WhatsAppYarApiService::isSessionBrokenStatus($status)) {
                    $sessions->discardRemoteSession($api, $sessionId);
                    $sessions->clearLocalSession($orgId);
                    $resolved = $sessions->resolveOrCreateSession($api, $org);
                    $sessionId = $resolved['session_id'];
                    $api->ensureSessionStarted($sessionId);
                    $data = $api->getQrCode($sessionId);
                    $qrCode = $data['qrCode'] ?? $data['data']['qrCode'] ?? null;
                    $status = WhatsAppYarApiService::normalizeSessionStatus($data);
                }
            }

            return response()->json([
                'ok' => true,
                'qrCode' => $qrCode,
                'status' => $status !== '' ? $status : ($data['status'] ?? $data['data']['status'] ?? null),
                'session_recreated' => $resolved['recreated'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function status(WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): JsonResponse
    {
        $orgId = (int) Auth::user()->current_organization_id;
        $org = Organization::query()->find($orgId);
        $settings = WhatsAppSettings::get($orgId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '' || ! $org) {
            return response()->json(['ok' => true, 'connected' => false, 'status' => 'missing']);
        }

        $api = $api->forOrganization($orgId);

        try {
            $data = $api->getSession($sessionId);
        } catch (\Throwable $e) {
            if (WhatsAppYarApiService::isSessionNotFoundError($e)) {
                $sessions->clearLocalSession($orgId);

                return response()->json([
                    'ok' => true,
                    'connected' => false,
                    'status' => 'missing',
                    'session_stale' => true,
                    'message' => 'Session was removed on WhatsAppYar. Click connect to create a new one.',
                ]);
            }

            return response()->json(['ok' => false, 'connected' => false, 'message' => $e->getMessage()], 422);
        }

        try {
            $status = strtolower((string) ($data['status'] ?? $data['data']['status'] ?? ''));
            $phoneRaw = $data['phone'] ?? $data['data']['phone'] ?? null;
            $phone = is_string($phoneRaw) ? $phoneRaw : (is_numeric($phoneRaw) ? (string) $phoneRaw : '');

            $connected = WhatsAppYarApiService::isSessionConnectedStatus($status);
            $update = [
                'status' => $status,
                'enabled' => $connected,
            ];
            if ($phone !== '') {
                $update['line_phone'] = preg_replace('/\D+/', '', $phone) ?? $phone;
            }
            WhatsAppSettings::set($update, $orgId);

            $webhookWarning = null;
            if ($connected) {
                $org = Organization::query()->find($orgId);
                if ($org) {
                    $webhookWarning = $this->tryEnsureWebhook($api->forOrganization($orgId), $org, $sessionId);
                }
            }

            return response()->json([
                'ok' => true,
                'connected' => $connected,
                'status' => $status,
                'phone' => $phone,
                'pushName' => $data['pushName'] ?? $data['data']['pushName'] ?? null,
                'webhook_warning' => $webhookWarning ?? null,
                'session_failed' => WhatsAppYarApiService::isSessionBrokenStatus($status),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'connected' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pairingCode(Request $request, WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $org = Organization::query()->find(Auth::user()->current_organization_id);
        if (! $org) {
            return response()->json(['ok' => false, 'message' => 'Organization not found.'], 422);
        }

        $orgId = (int) $org->id;
        $api = $api->forOrganization($orgId);

        try {
            $resolved = $sessions->resolveOrCreateSession($api, $org);
            $sessionId = $resolved['session_id'];

            $api->ensureSessionStarted($sessionId);
            $api->waitForPairingEligibility($sessionId);
            $webhookWarning = $this->tryEnsureWebhook($api, $org, $sessionId);

            $result = $api->requestPairingCode($sessionId, $validated['phone']);
            $code = (string) (
                $result['pairingCode']
                ?? $result['code']
                ?? $result['data']['pairingCode']
                ?? $result['data']['code']
                ?? ''
            );

            return response()->json([
                'ok' => true,
                'pairing_code' => $code,
                'session_recreated' => $resolved['recreated'],
                'webhook_warning' => $webhookWarning,
                'message' => $code !== ''
                    ? 'Enter this code in WhatsApp → Linked devices → Link with phone number.'
                    : 'Pairing code requested. Check WhatsApp on your phone.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function resetSession(WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): RedirectResponse
    {
        $org = Organization::query()->find(Auth::user()->current_organization_id);
        if (! $org) {
            return redirect()->route('settings.index', ['tab' => 'whatsapp'])
                ->with('error', 'Organization not found.');
        }

        if (! WhatsAppSettings::isConfigured($org->id)) {
            return redirect()->route('settings.index', ['tab' => 'whatsapp'])
                ->with('error', 'WhatsAppYar API key is not configured.');
        }

        try {
            $sessions->forceResetSession($api->forOrganization($org->id), $org);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp reset session failed', ['error' => $e->getMessage(), 'org_id' => $org->id]);

            return redirect()->route('settings.index', ['tab' => 'whatsapp'])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('settings.index', ['tab' => 'whatsapp'])
            ->with('success', 'WhatsApp session reset. Connect again with QR or pairing code.');
    }

    public function disconnect(WhatsAppYarApiService $api, WhatsAppSessionManager $sessions): RedirectResponse
    {
        $orgId = (int) Auth::user()->current_organization_id;
        $settings = WhatsAppSettings::get($orgId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        $webhookId = trim((string) ($settings['webhook_id'] ?? ''));

        try {
            $client = $api->forOrganization($orgId);
            if ($sessionId !== '') {
                if ($webhookId !== '') {
                    try {
                        $client->deleteWebhook($sessionId, $webhookId);
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                $sessions->discardRemoteSession($client, $sessionId);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp disconnect partial failure', ['error' => $e->getMessage()]);
        }

        $sessions->clearLocalSession($orgId);

        return redirect()->route('settings.index', ['tab' => 'whatsapp'])
            ->with('success', 'WhatsApp disconnected successfully.');
    }

    public function test(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $validated = $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        $message = $validated['test_message']
            ?? 'This is a test message from RoniCRM. If you received this, WhatsAppYar integration is working.';

        $result = $whatsapp->sendMessage($validated['test_phone'], $message);
        if ($result['success']) {
            return redirect()->back()->with('success', 'Test WhatsApp message sent to '.$validated['test_phone'].'.');
        }

        return redirect()->back()->with('error', 'Failed to send test message: '.($result['error'] ?? 'Unknown error'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'api_key' => 'nullable|string|max:500',
        ]);

        $payload = [];
        if (array_key_exists('enabled', $validated)) {
            $payload['enabled'] = (bool) $validated['enabled'];
        }
        if (array_key_exists('api_key', $validated)) {
            $payload['api_key'] = trim((string) ($validated['api_key'] ?? ''));
        }

        WhatsAppSettings::set($payload);

        return redirect()->back()->with('success', 'WhatsApp settings updated successfully.');
    }

    protected function tryEnsureWebhook(WhatsAppYarApiService $api, Organization $org, string $sessionId): ?string
    {
        $webhookUrl = WhatsAppSettings::webhookUrl($org);
        if (! WhatsAppSettings::isWebhookUrlPubliclyReachable($webhookUrl)) {
            WhatsAppSettings::set([
                'webhook_pending' => true,
                'webhook_error' => 'Webhook URL is not publicly reachable. On local development use ngrok and set WHATSAPPYAR_WEBHOOK_URL to your public HTTPS URL.',
            ], $org->id);

            return WhatsAppSettings::get($org->id)['webhook_error'];
        }

        try {
            $this->ensureWebhook($api, $org, $sessionId);
            WhatsAppSettings::set([
                'webhook_pending' => false,
                'webhook_error' => '',
            ], $org->id);

            return null;
        } catch (\Throwable $e) {
            WhatsAppSettings::set([
                'webhook_pending' => true,
                'webhook_error' => $e->getMessage(),
            ], $org->id);

            return $e->getMessage();
        }
    }

    protected function ensureWebhook(WhatsAppYarApiService $api, Organization $org, string $sessionId): void
    {
        $settings = WhatsAppSettings::get($org->id);
        $webhookUrl = WhatsAppSettings::webhookUrl($org);
        $existingWebhookId = trim((string) ($settings['webhook_id'] ?? ''));

        if ($existingWebhookId !== '') {
            return;
        }

        $secret = trim((string) ($settings['webhook_secret'] ?? ''));
        if ($secret === '') {
            $secret = WhatsAppYarApiService::generateWebhookSecret();
        }

        $created = $api->createWebhook($sessionId, $webhookUrl, $secret);
        $webhookId = (string) ($created['id'] ?? $created['data']['id'] ?? '');

        WhatsAppSettings::set([
            'webhook_id' => $webhookId,
            'webhook_secret' => $secret,
            'enabled' => true,
        ], $org->id);
    }
}
