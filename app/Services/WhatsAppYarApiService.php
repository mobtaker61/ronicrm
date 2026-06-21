<?php

namespace App\Services;

use App\Support\WhatsAppSettings;
use App\Support\WhatsAppYarUrlDefaults;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppYarApiService
{
    protected ?int $organizationId;

    public function __construct(?int $organizationId = null)
    {
        $this->organizationId = $organizationId;
    }

    public function forOrganization(?int $organizationId): self
    {
        $clone = clone $this;
        $clone->organizationId = $organizationId;

        return $clone;
    }

    public function isConfigured(): bool
    {
        return WhatsAppSettings::isConfigured($this->organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(string $name, ?array $config = null): array
    {
        $payload = ['name' => $name];
        if ($config !== null) {
            $payload['config'] = $config;
        }

        return $this->request('post', '/sessions', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function listSessions(): array
    {
        return $this->request('get', '/sessions');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.$sessionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function startSession(string $sessionId): array
    {
        return $this->request('post', '/sessions/'.$sessionId.'/start');
    }

    /**
     * @param  array<string, mixed>  $sessionPayload
     */
    public static function normalizeSessionStatus(array $sessionPayload): string
    {
        return strtolower((string) (
            $sessionPayload['status']
            ?? $sessionPayload['data']['status']
            ?? ''
        ));
    }

    public static function isSessionConnectedStatus(string $status): bool
    {
        return in_array($status, ['ready', 'authenticated', 'connected', 'open'], true);
    }

    public static function isSessionRunningStatus(string $status): bool
    {
        return in_array($status, [
            'initializing',
            'qr_ready',
            'authenticating',
            'starting',
            'started',
            'scanning',
            'qr',
            'pairing',
            'ready',
            'connected',
            'open',
        ], true);
    }

    public static function isSessionBrokenStatus(string $status): bool
    {
        return in_array($status, [
            'failed',
            'error',
            'disconnected',
            'closed',
            'stopped',
            'killed',
            'timeout',
        ], true);
    }

    public static function isPairingEligibleStatus(string $status): bool
    {
        return in_array($status, [
            'initializing',
            'qr_ready',
            'starting',
            'started',
            'scanning',
            'qr',
            'pairing',
        ], true);
    }

    public static function isSessionNotFoundError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'unknown session');
    }

    /**
     * Start or restart session for QR/pairing. Broken sessions are restarted.
     *
     * @return array<string, mixed>
     */
    public function ensureSessionStarted(string $sessionId): array
    {
        try {
            $session = $this->getSession($sessionId);
        } catch (\Throwable $e) {
            if (self::isSessionNotFoundError($e)) {
                throw $e;
            }

            throw $e;
        }

        $status = self::normalizeSessionStatus($session);

        if (self::isSessionConnectedStatus($status)) {
            return $session;
        }

        if (self::isSessionBrokenStatus($status)) {
            try {
                $this->forceKillSession($sessionId);
            } catch (\Throwable) {
                // ignore
            }
            try {
                $this->stopSession($sessionId);
            } catch (\Throwable) {
                // ignore
            }

            return $this->startSession($sessionId);
        }

        if (self::isSessionRunningStatus($status)) {
            return $session;
        }

        try {
            return $this->startSession($sessionId);
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'already started') || str_contains($message, 'already running')) {
                return $this->getSession($sessionId);
            }

            throw $e;
        }
    }

    /**
     * Wait until session engine is ready for pairing-code request (WhatsAppYar requires started + qr_ready).
     *
     * @return array<string, mixed>
     */
    public function waitForPairingEligibility(string $sessionId, int $maxSeconds = 25): array
    {
        $deadline = time() + max(5, $maxSeconds);
        $last = [];

        while (time() < $deadline) {
            $last = $this->getSession($sessionId);
            $status = self::normalizeSessionStatus($last);

            if (self::isSessionConnectedStatus($status)) {
                throw new \RuntimeException('Session is already authenticated.');
            }

            if (self::isSessionBrokenStatus($status)) {
                $reason = (string) ($last['lastError'] ?? $last['data']['lastError'] ?? '');
                throw new \RuntimeException($reason !== '' ? $reason : 'Session failed before pairing could start.');
            }

            if ($status === 'created' || $status === '') {
                $this->startSession($sessionId);
            } elseif (self::isPairingEligibleStatus($status)) {
                sleep(3);

                return $last;
            }

            usleep(500_000);
        }

        throw new \RuntimeException('Session did not become ready for pairing in time. Use "Reset session" and try again.');
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPairingCode(string $sessionId, string $phoneNumber): array
    {
        $digits = self::normalizePhoneDigits($phoneNumber);
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
            throw new \InvalidArgumentException('Enter phone in international format without + (e.g. 971562858133).');
        }

        return $this->request('post', '/sessions/'.$sessionId.'/pairing-code', [
            'phoneNumber' => $digits,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listGroups(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.$sessionId.'/groups');
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveGroup(string $sessionId, string $groupId): array
    {
        $encoded = rawurlencode($groupId);

        return $this->request('post', '/sessions/'.$sessionId.'/groups/'.$encoded.'/leave');
    }

    public function forceKillSession(string $sessionId): array
    {
        return $this->request('post', '/sessions/'.$sessionId.'/force-kill');
    }

    /**
     * @return array<string, mixed>
     */
    public function stopSession(string $sessionId): array
    {
        return $this->request('post', '/sessions/'.$sessionId.'/stop');
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSession(string $sessionId): array
    {
        return $this->request('delete', '/sessions/'.$sessionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getQrCode(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.$sessionId.'/qr');
    }

    /**
     * @return array<string, mixed>
     */
    public function createWebhook(string $sessionId, string $url, ?string $secret = null, array $events = []): array
    {
        $payload = [
            'url' => $url,
            'events' => $events !== [] ? $events : [
                'message.received',
                'session.status',
                'session.authenticated',
                'session.disconnected',
                'group.join',
                'group.update',
                'group.leave',
            ],
            'retryCount' => 3,
        ];

        if ($secret !== null && $secret !== '') {
            $payload['secret'] = $secret;
        }

        return $this->request('post', '/sessions/'.$sessionId.'/webhooks', $payload);
    }

    /**
     * @return array<int, mixed>
     */
    public function listWebhooks(string $sessionId): array
    {
        $result = $this->request('get', '/sessions/'.$sessionId.'/webhooks');

        return is_array($result) ? $result : [];
    }

    public function deleteWebhook(string $sessionId, string $webhookId): void
    {
        $this->request('delete', '/sessions/'.$sessionId.'/webhooks/'.$webhookId);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $sessionId, string $chatId, string $text): array
    {
        return $this->request('post', '/sessions/'.$sessionId.'/messages/send-text', [
            'chatId' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMedia(string $sessionId, string $endpoint, string $chatId, string $url, ?string $caption = null, ?string $mimetype = null, ?string $filename = null): array
    {
        $payload = [
            'chatId' => $chatId,
            'url' => $url,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }
        if ($mimetype !== null && $mimetype !== '') {
            $payload['mimetype'] = $mimetype;
        }
        if ($filename !== null && $filename !== '') {
            $payload['filename'] = $filename;
        }

        return $this->request('post', '/sessions/'.$sessionId.'/messages/'.$endpoint, $payload);
    }

    public static function phoneToChatId(string $phone): string
    {
        $phone = trim($phone);
        if (str_contains($phone, '@')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits.'@c.us';
    }

    public static function resolveChatId(string $phoneOrChatId): string
    {
        return self::phoneToChatId($phoneOrChatId);
    }

    public static function chatIdToPhone(string $chatId): string
    {
        $chatId = trim($chatId);
        if (str_contains($chatId, '@')) {
            $chatId = explode('@', $chatId, 2)[0] ?? $chatId;
        }

        return preg_replace('/\D+/', '', $chatId) ?? '';
    }

    public static function isGroupChatId(string $chatId): bool
    {
        return str_ends_with(strtolower(trim($chatId)), '@g.us');
    }

    public static function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * فقط چت خصوصی یک‌به‌یک — نه گروه، استاتوس، کانال یا broadcast.
     */
    public static function isPrivateDirectChatId(string $chatId): bool
    {
        $chatId = strtolower(trim($chatId));
        if ($chatId === '') {
            return false;
        }
        if (str_contains($chatId, '@broadcast')) {
            return false;
        }
        if (str_starts_with($chatId, 'status@')) {
            return false;
        }
        if (str_ends_with($chatId, '@g.us')) {
            return false;
        }
        if (str_ends_with($chatId, '@newsletter')) {
            return false;
        }

        return str_ends_with($chatId, '@c.us') || str_ends_with($chatId, '@lid');
    }

    public static function isValidInboxPeerPhone(string $phone, ?string $linePhone = null): bool
    {
        $phone = self::normalizePhoneDigits($phone);
        if ($phone === '' || strlen($phone) < 7 || strlen($phone) > 14) {
            return false;
        }

        if ($linePhone !== null && $linePhone !== '') {
            $line = self::normalizePhoneDigits($linePhone);
            if ($line !== '' && $phone === $line) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function listChats(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.$sessionId.'/chats');
    }

    /**
     * @return array<string, mixed>
     */
    public function listContacts(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.$sessionId.'/contacts');
    }

    /**
     * @return array<string, mixed>
     */
    public function getChatHistory(string $sessionId, string $chatId, int $limit = 80): array
    {
        $encoded = rawurlencode($chatId);

        return $this->request('get', '/sessions/'.$sessionId.'/messages/'.$encoded.'/history', null, [
            'limit' => max(1, min(200, $limit)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function markChatRead(string $sessionId, string $chatId): array
    {
        return $this->request('post', '/sessions/'.$sessionId.'/chats/read', [
            'chatId' => $chatId,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $query
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $payload = null, ?array $query = null): array
    {
        $apiKey = WhatsAppSettings::resolveApiKey($this->organizationId);
        if ($apiKey === '') {
            throw new \RuntimeException('WhatsAppYar API key is not configured.');
        }

        $url = WhatsAppYarUrlDefaults::apiUrl($path);
        $client = Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $apiKey,
            ]);

        if ($query !== null && $query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        /** @var Response $response */
        $response = match (strtolower($method)) {
            'get' => $client->get($url),
            'post' => $client->post($url, $payload ?? []),
            'put' => $client->put($url, $payload ?? []),
            'delete' => $client->delete($url),
            default => throw new \InvalidArgumentException('Unsupported HTTP method: '.$method),
        };

        if ($response->status() === 204) {
            return ['success' => true];
        }

        $json = $response->json();
        if (! $response->successful()) {
            $message = is_array($json)
                ? ($json['message'] ?? $json['error'] ?? $response->body())
                : $response->body();
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }
            Log::warning('WhatsAppYar API error', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->status(),
                'body' => $json,
            ]);
            throw new \RuntimeException(is_string($message) && $message !== '' ? $message : 'WhatsAppYar API request failed.');
        }

        return is_array($json) ? $json : ['data' => $json];
    }

    public static function generateWebhookSecret(): string
    {
        return Str::random(40);
    }
}
