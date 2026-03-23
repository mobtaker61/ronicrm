<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;
    protected string $apiBase = 'https://api.telegram.org/bot';
    protected bool $enabled;

    public function __construct()
    {
        $settings = Setting::getScoped('telegram', []);
        $this->botToken = $settings['bot_token'] ?? '';
        $this->enabled = $settings['enabled'] ?? false;
    }

    public function getMe(): array
    {
        return $this->getMeWithToken($this->botToken);
    }

    /**
     * Verify a bot token (e.g. for testing before saving). Does not send any message.
     */
    public function getMeWithToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'Telegram bot token is empty'];
        }

        try {
            $response = Http::timeout(15)->get($this->apiBase . $token . '/getMe');
            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                return [
                    'success' => true,
                    'username' => $data['result']['username'] ?? null,
                ];
            }
            return [
                'success' => false,
                'error' => $data['description'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram getMe error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a message to a Telegram chat_id.
     *
     * @param string $chatId Telegram chat_id (user id in private chats)
     * @param string $message Text to send
     * @param string|null $fileUrl Optional URL of file (photo, document, etc.)
     * @return array { success: bool, error?: string, message_id?: int }
     */
    public function sendMessage(string $chatId, string $message, ?string $fileUrl = null): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Telegram is not enabled'];
        }

        if (empty($this->botToken)) {
            return ['success' => false, 'error' => 'Telegram bot token is not configured'];
        }

        $url = $this->apiBase . $this->botToken;

        try {
            if ($fileUrl) {
                // Send as photo if image-like, otherwise document
                $isImage = $this->isImageUrl($fileUrl);
                $method = $isImage ? 'sendPhoto' : 'sendDocument';
                $key = $isImage ? 'photo' : 'document';
                $response = Http::timeout(30)->post($url . '/' . $method, [
                    'chat_id' => $chatId,
                    $key => $fileUrl,
                    'caption' => $message ?: null,
                ]);
            } else {
                $response = Http::timeout(30)->post($url . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
            }

            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                return [
                    'success' => true,
                    'message_id' => $data['result']['message_id'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $data['description'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setWebhook(string $webhookUrl, ?string $token = null): array
    {
        $token = trim($token ?? $this->botToken ?? '');
        if ($token === '') {
            return ['success' => false, 'error' => 'Bot token not set'];
        }
        try {
            $response = Http::timeout(15)->post($this->apiBase . $token . '/setWebhook', [
                'url' => $webhookUrl,
            ]);
            $data = $response->json();
            return [
                'success' => (bool) ($data['ok'] ?? false),
                'error' => $data['description'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Telegram setWebhook error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function isImageUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }
}
